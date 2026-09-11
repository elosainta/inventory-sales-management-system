<?php

namespace App\Http\Controllers;

use App\Domain\Recipes\Actions\SaveRecipe;
use App\Http\Requests\StoreRndEntryRequest;
use App\Http\Requests\StoreRndRecipeRequest;
use App\Models\InventoryItem;
use App\Models\RndEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Domain\Costing\Actions\CostingSheetLines;

class RndEntryController extends Controller
{
    public function index()
    {
        Gate::authorize('view-rnd');

        // Pending first: the Owner's job on this page is to clear the queue,
        // and a decided entry is history. Same ordering as Leave.
        $entries = RndEntry::with(['creator', 'decider', 'recipe', 'lines.inventoryItem'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('purchased_on')
            ->orderByDesc('id')
            ->get();

        return view('rnd.index', [
            'entries'  => $entries,
            'items'    => InventoryItem::orderBy('name')->get(),
            'pending'  => $entries->where('status', RndEntry::STATUS_PENDING),
            'approved' => $entries->where('status', RndEntry::STATUS_APPROVED),
            'byMenu'   => $this->spendPerMenu($entries),
        ]);
    }

    public function store(StoreRndEntryRequest $request)
    {
        Gate::authorize('manage-rnd');

        $data  = $request->validated();
        $lines = $data['lines'];
        unset($data['lines']);

        $entry = DB::transaction(function () use ($data, $lines) {
            $entry = RndEntry::create($data + [
                'status'     => RndEntry::STATUS_PENDING,
                'created_by' => auth()->id(),
            ]);

            CostingSheetLines::write($entry, $lines);

            // Off the shelf now, not on approval: the chef has already taken
            // it, and stock that lags a review is stock nobody can trust.
            CostingSheetLines::deduct($lines);

            return $entry;
        });

        return back()->with('success', "Recorded, and {$entry->lines->count()} ingredient(s) taken off stock. Waiting for the Owner to review the spend.");
    }

    public function update(StoreRndEntryRequest $request, RndEntry $rndEntry)
    {
        Gate::authorize('manage-rnd');

        // An approved entry is frozen. Enforced here rather than only by hiding
        // the button, because a form is not a boundary — without this line a
        // hand-rolled POST rewrites the price under a signed-off approval.
        if ($rndEntry->isLocked()) {
            return back()->with('error', 'This R&D has been approved and can no longer be edited.');
        }

        $data  = $request->validated();
        $lines = $data['lines'];
        unset($data['lines']);

        // Editing does NOT move stock again — the same rule as Sales, Wastage
        // and Production, and for the same reason: a second deduction on every
        // correction would make a typo cost real stock twice. That now covers
        // a line ADDED by an edit as well, which deducts nothing. Correct the
        // shelf with a Tally; the modal says so.
        //
        // Editing a rejected entry is the correction the rejection asked for,
        // so it goes back in the queue rather than staying rejected with new
        // figures nobody has looked at.
        if ($rndEntry->status === RndEntry::STATUS_REJECTED) {
            $data += [
                'status'     => RndEntry::STATUS_PENDING,
                'decided_by' => null,
                'decided_at' => null,
            ];
        }

        DB::transaction(function () use ($rndEntry, $data, $lines) {
            $rndEntry->update($data);
            $rndEntry->lines()->delete();
            CostingSheetLines::write($rndEntry, $lines);
        });

        return back()->with('success', $rndEntry->isPending() && $rndEntry->wasChanged('status')
            ? 'R&D updated and sent back to the Owner.'
            : 'R&D updated.');
    }

    public function destroy(RndEntry $rndEntry)
    {
        Gate::authorize('manage-rnd');

        if ($rndEntry->isLocked()) {
            return back()->with('error', 'This R&D has been approved and can no longer be deleted.');
        }

        $rndEntry->delete();

        // Deleting does not put the stock back, same as deleting a production
        // batch or a wastage entry. Said out loud rather than left as a
        // surprise, because this sheet moved real stock when it was created.
        return back()->with('success', 'R&D removed. Stock was not put back — use a Tally if the shelf needs correcting.');
    }

    public function approve(RndEntry $rndEntry)
    {
        Gate::authorize('decide-rnd');

        return $this->decide($rndEntry, RndEntry::STATUS_APPROVED, 'R&D approved.');
    }

    public function reject(RndEntry $rndEntry)
    {
        Gate::authorize('decide-rnd');

        return $this->decide($rndEntry, RndEntry::STATUS_REJECTED, 'R&D rejected.');
    }

    /**
     * Write an approved trial up as a real dish.
     *
     * The ingredients and their quantities come off the sheet, never off the
     * form — the recipe is meant to be what the trial actually used, and
     * letting the form supply them would make it an ordinary "new recipe"
     * screen wearing an R&D label. The miscellaneous percentage comes off the
     * sheet too, so the dish is costed the way the trial was costed.
     *
     * Gated on manage-recipes rather than decide-rnd: the approval is the
     * Owner's, but writing the dish up is recipe work and this is the authority
     * that already governs it.
     */
    public function createRecipe(StoreRndRecipeRequest $request, RndEntry $rndEntry, SaveRecipe $action)
    {
        Gate::authorize('manage-recipes');

        if (! $rndEntry->canBecomeRecipe()) {
            return back()->with('error', $rndEntry->recipe_id
                ? 'This trial is already a recipe.'
                : 'Only an approved trial with ingredients behind it can become a recipe.');
        }

        $recipe = $action->execute($request->validated() + [
            'misc_percent' => $rndEntry->misc_percent,
            'ingredients'  => $rndEntry->lines
                ->whereNotNull('inventory_item_id')
                ->map(fn ($line) => [
                    'inventory_item_id' => $line->inventory_item_id,
                    'quantity'          => (float) $line->quantity,
                ])
                ->values()
                ->all(),
        ]);

        // The link back, and what stops one trial becoming two recipes.
        $rndEntry->update(['recipe_id' => $recipe->id]);

        return redirect()->route('recipes.index')
            ->with('success', "\"{$recipe->name}\" created from the R&D trial. Check the ingredient quantities before it goes on the menu.");
    }

    public function exportPdf()
    {
        Gate::authorize('export-pdf');

        $entries = RndEntry::with(['creator', 'decider', 'recipe', 'lines'])
            ->orderByDesc('purchased_on')
            ->orderByDesc('id')
            ->get();

        $byMenu = $this->spendPerMenu($entries);

        $pdf = Pdf::loadView('pdfs.rnd', compact('entries', 'byMenu'));

        return $pdf->download('rnd-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * What each dish has cost to develop, dearest first.
     *
     * Grouped off the collection that is already loaded rather than with a
     * second query — the page holds every entry anyway, and one R&D list is
     * never big enough for that to be worth a GROUP BY.
     *
     * The figures are grand totals (ingredients plus the miscellaneous
     * overhead), which is what the costing sheet's own bottom line says a plate
     * came to. And they cover every trial regardless of status: rejecting one
     * means the kitchen does not pay for it, but the ingredients still left the
     * shelf, so a figure that quietly dropped rejected trials would understate
     * what the dish actually cost to arrive at.
     */
    private function spendPerMenu(\Illuminate\Support\Collection $entries): \Illuminate\Support\Collection
    {
        return $entries
            ->groupBy(fn (RndEntry $entry) => $entry->menu_name ?: '—')
            ->map(fn ($rows, $menu) => [
                'menu'     => $menu,
                'trials'   => $rows->count(),
                'approved' => $rows->where('status', RndEntry::STATUS_APPROVED)->sum('grand_total'),
                'pending'  => $rows->where('status', RndEntry::STATUS_PENDING)->sum('grand_total'),
                'total'    => $rows->sum('grand_total'),
                'recipe'   => $rows->firstWhere('recipe_id', '!=', null)?->recipe,
            ])
            ->sortByDesc('total')
            ->values();
    }

    private function decide(RndEntry $entry, string $status, string $message)
    {
        if (! $entry->isPending()) {
            return back()->with('error', 'This R&D has already been decided.');
        }

        // A decision is about the money. The stock left the shelf when the
        // sheet was recorded and a rejection does not put it back — the chef
        // still used it.
        $entry->update([
            'status'     => $status,
            'decided_by' => auth()->id(),
            'decided_at' => now(),
        ]);

        return back()->with('success', $message);
    }
}
