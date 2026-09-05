<?php

namespace App\Http\Controllers;

use App\Domain\Wastage\Actions\LogWastage;
use App\Http\Requests\StoreWastageEntryRequest;
use App\Models\WastageEntry;
use App\Models\InventoryItem;
use Illuminate\Support\Facades\Gate;
use Barryvdh\DomPDF\Facade\Pdf;

class WastageEntryController extends Controller
{
    public function index()
    {
        Gate::authorize('view-wastage');

        $range = request('range');
        $month = request('month', now()->format('Y-m'));

        $query = WastageEntry::with(['inventoryItem'])->orderBy('recorded_date', 'desc');

        if ($range === 'today') {
            $query->whereDate('recorded_date', today());
        } elseif ($range === 'week') {
            $query->whereBetween('recorded_date', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($range === 'month') {
            $query->whereYear('recorded_date', now()->year)->whereMonth('recorded_date', now()->month);
        } elseif ($range === 'year') {
            $query->whereYear('recorded_date', now()->year);
        } else {
            [$year, $mon] = explode('-', $month);
            $query->whereYear('recorded_date', $year)->whereMonth('recorded_date', $mon);
        }

        $entries   = $query->get();
        $items     = InventoryItem::orderBy('name')->get();
        $totalLost = $entries->sum('cost_lost');

        // Unfiltered count, so an empty month can say "nothing in this period"
        // instead of claiming there is no data at all.
        $totalOnRecord = \App\Models\WastageEntry::count();

        return view('wastage.index', compact('entries', 'items', 'month', 'totalLost', 'range', 'totalOnRecord'));
    }
    public function store(StoreWastageEntryRequest $request, LogWastage $action)
    {
        Gate::authorize('manage-wastage');

        $action->execute($request->validated());

        return back()->with('success', 'Wastage logged.');
    }

    public function update(StoreWastageEntryRequest $request, WastageEntry $wastageEntry)
    {
        Gate::authorize('manage-wastage');

        $data = $request->validated();
        $item = InventoryItem::findOrFail($data['inventory_item_id']);
        $data['cost_lost'] = round($item->unit_cost * $data['quantity_wasted'], 2);

        $wastageEntry->update($data);

        return back()->with('success', 'Wastage updated.');
    }

    public function destroy(WastageEntry $wastageEntry)
    {
        Gate::authorize('manage-wastage');

        $wastageEntry->delete();
        return back()->with('success', 'Entry removed.');
    }

    public function exportPdf()
    {
        Gate::authorize('export-pdf');

        $entries = WastageEntry::with(['inventoryItem'])
            ->orderBy('recorded_date', 'desc')
            ->get();

        $pdf = Pdf::loadView('pdfs.wastage', compact('entries'));
        return $pdf->download('wastage-' . now()->format('Y-m-d') . '.pdf');
    }
}