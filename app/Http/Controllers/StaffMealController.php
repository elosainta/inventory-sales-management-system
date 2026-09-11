<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffMealRequest;
use App\Models\InventoryItem;
use App\Models\StaffMeal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Domain\Costing\Actions\CostingSheetLines;

/**
 * Staff meals — what the kitchen cooked for its own people, and what it cost.
 *
 * Recording one DEDUCTS every line from live stock immediately, exactly as an
 * R&D trial does: the team has already eaten it, so the shelf should say so.
 * Like every other deduction in this app it is not reversed by an edit or a
 * delete, and not repeated — the shelf is corrected with a Tally.
 *
 * Nothing here is approved, though. A meal is a fact, not a request: there is
 * no status column and no decide- gate, because nobody signs off lunch.
 */
class StaffMealController extends Controller
{
    public function index()
    {
        Gate::authorize('view-staff-meal');

        $meals = StaffMeal::with(['creator', 'lines.inventoryItem'])
            ->orderByDesc('meal_date')
            ->orderByDesc('id')
            ->get();

        return view('staff-meals.index', [
            'meals'   => $meals,
            'items'   => InventoryItem::orderBy('name')->get(),
            'byMonth' => $this->spendPerMonth($meals),
        ]);
    }

    public function store(StoreStaffMealRequest $request)
    {
        Gate::authorize('manage-staff-meal');

        $data  = $request->validated();
        $lines = $data['lines'];
        unset($data['lines']);

        $meal = DB::transaction(function () use ($data, $lines) {
            $meal = StaffMeal::create($data + ['created_by' => auth()->id()]);

            CostingSheetLines::write($meal, $lines);

            // The team has eaten it, so the shelf should say so. Same rule as
            // an R&D trial, and inside the same transaction as the sheet: a
            // meal that was written but never deducted would overstate stock
            // with nothing on screen to show why.
            CostingSheetLines::deduct($lines);

            return $meal;
        });

        return back()->with('success', "Staff meal recorded, and {$meal->lines->count()} ingredient(s) taken off stock.");
    }

    public function update(StoreStaffMealRequest $request, StaffMeal $staffMeal)
    {
        Gate::authorize('manage-staff-meal');

        $data  = $request->validated();
        $lines = $data['lines'];
        unset($data['lines']);

        // Editing does NOT move stock again — the same rule as Sales, Wastage,
        // Production and R&D, and for the same reason: a second deduction on
        // every correction would make one typo cost real stock twice. That
        // covers a line ADDED by an edit as well, which deducts nothing, and a
        // line removed, which puts nothing back. The modal says so; the shelf
        // is corrected with a Tally.
        //
        // An edit replaces the whole sheet, so a line removed in the modal
        // actually goes.
        DB::transaction(function () use ($staffMeal, $data, $lines) {
            $staffMeal->update($data);
            $staffMeal->lines()->delete();
            CostingSheetLines::write($staffMeal, $lines);
        });

        return back()->with('success', 'Staff meal updated.');
    }

    public function destroy(StaffMeal $staffMeal)
    {
        Gate::authorize('manage-staff-meal');

        $staffMeal->delete();

        // Deleting does not put the stock back, same as deleting an R&D sheet,
        // a production batch or a wastage entry. Said out loud rather than left
        // as a surprise, because this sheet moved real stock when it was made.
        return back()->with('success', 'Staff meal removed. Stock was not put back — use a Tally if the shelf needs correcting.');
    }

    public function exportPdf()
    {
        Gate::authorize('export-pdf');

        $meals = StaffMeal::with(['creator', 'lines'])
            ->orderByDesc('meal_date')
            ->orderByDesc('id')
            ->get();

        $pdf = Pdf::loadView('pdfs.staff-meals', [
            'meals'   => $meals,
            'byMonth' => $this->spendPerMonth($meals),
        ]);

        return $pdf->download('staff-meals-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * What feeding the team cost, month by month, newest first.
     *
     * Grouped off the collection the page already holds rather than with a
     * second query — one staff-meal list is never big enough for a GROUP BY to
     * earn its keep, and the grand totals are derived in PHP anyway.
     *
     * There is no per-head figure: the headcount was dropped on 2026-09-09,
     * and with it the only thing that could divide a month's spend by people.
     */
    private function spendPerMonth(Collection $meals): Collection
    {
        return $meals
            ->groupBy(fn (StaffMeal $meal) => $meal->meal_date->format('Y-m'))
            ->map(fn ($rows) => [
                // Read off a row's own date rather than by re-parsing the
                // "Y-m" key: Carbon fills a missing day from today, so
                // parsing "2026-02" on the 31st lands in March.
                'month' => $rows->first()->meal_date->format('F Y'),
                'meals' => $rows->count(),
                'total' => $rows->sum('grand_total'),
            ])
            ->sortKeysDesc()
            ->values();
    }
}
