<?php

namespace App\Http\Controllers;

use App\Domain\Purchasing\Actions\LogPurchase;
use App\Http\Requests\CompletePurchasesRequest;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\UpdatePurchaseRequest;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\InventoryItem;
use App\Support\Period;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PurchaseController extends Controller
{
    public function index()
    {
        Gate::authorize('view-purchases');

        $range = request('range');
        $month = request('month', now()->format('Y-m'));

        $query = Purchase::with(['supplier', 'lines.inventoryItem'])->orderBy('purchase_date', 'desc');

        Period::filter($query, $range, $month, 'purchase_date');

        $purchases  = $query->get();
        $suppliers  = Supplier::orderBy('name')->get();
        $items      = InventoryItem::orderBy('name')->get();
        $totalSpend = $purchases->sum('total_amount');

        // Unfiltered count, so an empty month can say "nothing in this period"
        // instead of claiming there is no data at all.
        $totalOnRecord = \App\Models\Purchase::count();

        return view('purchases.index', compact('purchases', 'suppliers', 'items', 'month', 'totalSpend', 'range', 'totalOnRecord'));
    }

    public function store(StorePurchaseRequest $request, LogPurchase $action)
{
    Gate::authorize('manage-purchases');

    $data            = $request->validated();
    $data['user_id'] = auth()->id();

    $action->execute($data, $request->file('receipt'));

    return back()->with('success', 'Purchase logged.');
}

    public function update(UpdatePurchaseRequest $request, Purchase $purchase)
    {
        Gate::authorize('manage-purchases');

        $purchase->update($request->validated());

        return back()->with('success', 'Purchase updated.');
    }

    public function show(Purchase $purchase)
    {
        Gate::authorize('view-purchases');

        $purchase->load(['supplier', 'lines.inventoryItem', 'user', 'invoiceScan']);

        return view('purchases.show', compact('purchase'));
    }

    public function receipt(Purchase $purchase)
    {
        Gate::authorize('view-purchases');

        abort_if(! $purchase->receipt_path || ! Storage::exists($purchase->receipt_path), 404);

        return Storage::response($purchase->receipt_path);
    }

public function destroy(Purchase $purchase)
{
    Gate::authorize('delete-entries');

    DB::transaction(function () use ($purchase) {
        foreach ($purchase->lines()->with('inventoryItem')->get() as $line) {
            $item = $line->inventoryItem;
            if (!$item) continue;

            $newQty = max(0, $item->quantity_on_hand - $line->quantity);
            $item->quantity_on_hand = $newQty;
            $item->save();
        }

        // One supplier Upload is the receipt of every purchase it completed,
        // so the photo goes only with the last purchase still showing it.
        if ($purchase->receipt_path && ! Purchase::where('receipt_path', $purchase->receipt_path)->whereKeyNot($purchase->id)->exists()) {
            Storage::delete($purchase->receipt_path);
        }

        $purchase->delete();
    }, 3); // retried on a write clash; see LogProduction

    return back()->with('success', 'Purchase removed and stock reversed.');
}
    public function toggleStatus(Purchase $purchase)
    {
        Gate::authorize('manage-purchases');

        $purchase->update([
            'status' => $purchase->status === 'completed' ? 'pending' : 'completed',
        ]);

        return back()->with('success', 'Purchase status updated.');
    }

    /**
     * The Upload button on a supplier's dropdown (the Owner, 2026-10-02): one
     * photo — a statement or a payment slip — completes every pending
     * purchase the button was shown beside, instead of pressing each Pending
     * button in turn. It becomes the receipt of each that had none, as one
     * shared file; destroy() keeps it until the last of them goes.
     */
    public function complete(CompletePurchasesRequest $request)
    {
        Gate::authorize('manage-purchases');

        $pending = Purchase::whereIn('id', $request->validated('purchase_ids'))
            ->where('status', 'pending')
            ->get();

        if ($pending->isEmpty()) {
            return back()->with('error', 'Nothing here is pending any more.');
        }

        // Stored only when some purchase will show it, so no file is orphaned.
        $photo = $pending->contains(fn ($p) => ! $p->receipt_path) ? $request->file('photo')->store('receipts') : null;

        DB::transaction(function () use ($pending, $photo) {
            foreach ($pending as $purchase) {
                $purchase->update(['status' => 'completed', 'receipt_path' => $purchase->receipt_path ?? $photo]);
            }
        });

        $count = $pending->count();

        return back()->with('success', "{$count} " . Str::plural('purchase', $count) . ' marked completed.');
    }

    public function exportPdf()
    {
        Gate::authorize('export-pdf');

        // A month per sheet, and a line per supplier: what is owed to whom.
        // It was a page per supplier listing every ingredient, which on live
        // ran to fourteen sheets to answer a question about five numbers.
        //
        // Only the line totals are needed, so inventoryItem is not loaded.
        $purchases = Purchase::with(['supplier', 'lines'])->get();

        // The label comes off a row's own date and never by re-parsing the
        // Y-m key: Carbon fills a missing day from today, so parsing 2026-02
        // on the 31st lands in March.
        $months = $purchases
            ->groupBy(fn ($purchase) => $purchase->purchase_date->format('Y-m'))
            ->sortKeys()
            ->map(fn ($group) => $this->statusSplit($group) + [
                'label'     => $group->first()->purchase_date->format('F Y'),
                'count'     => $group->count(),
                'suppliers' => $this->spendPerSupplier($group),
            ]);

        $summary = $this->statusSplit($purchases) + ['count' => $purchases->count()];

        $pdf = Pdf::loadView('pdfs.purchases', compact('months', 'summary'));

        return $pdf->download('purchases-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Spend, and how much of it is still unpaid.
     *
     * "Paid" here is this kitchen's own Completed/Pending status on a
     * purchase, which is what the Purchase log sets. It is NOT the balance on
     * the supplier's bill in Bukku — that is the Supplier Bills page, and the
     * two can legitimately disagree while a delivery is logged but not yet
     * settled. Both screens name which one they are showing.
     *
     * Summed off the lines rather than total_amount, so every figure in this
     * report comes from the same place.
     */
    private function statusSplit(Collection $purchases): array
    {
        $spent = fn (Collection $set) => (float) $set->flatMap->lines->sum('line_total');

        return [
            'total'   => $spent($purchases),
            'paid'    => $spent($purchases->where('status', 'completed')),
            'pending' => $spent($purchases->where('status', 'pending')),
        ];
    }

    /** Most owed first, so a month opens on whoever is waiting for money. */
    private function spendPerSupplier(Collection $purchases): Collection
    {
        return $purchases
            ->groupBy(fn ($purchase) => $purchase->supplier->name ?? 'No supplier')
            ->map(fn ($group) => $this->statusSplit($group) + ['count' => $group->count()])
            ->sortByDesc(fn ($row) => [$row['pending'], $row['total']]);
    }
}
