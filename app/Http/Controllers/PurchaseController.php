<?php

namespace App\Http\Controllers;

use App\Domain\Purchasing\Actions\LogPurchase;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\UpdatePurchaseRequest;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\InventoryItem;
use App\Support\Period;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

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

        if ($purchase->receipt_path) {
            Storage::delete($purchase->receipt_path);
        }

        $purchase->delete();
    });

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

    public function exportPdf()
    {
        Gate::authorize('export-pdf');

        // One page per supplier, not one row per purchase order: what the Owner
        // wants off this PDF is "what did we buy from X and what did it cost",
        // which lives on the line items, not the order headers.
        $suppliers = Purchase::with(['supplier', 'lines.inventoryItem'])
            ->get()
            ->groupBy('supplier_id')
            ->map(fn ($purchases) => [
                'supplier' => $purchases->first()->supplier,
                'lines'    => $purchases->flatMap->lines,
            ])
            ->sortBy(fn ($group) => $group['supplier']->name ?? '')
            ->values();

        $pdf = Pdf::loadView('pdfs.purchases', compact('suppliers'));
        return $pdf->download('purchases-' . now()->format('Y-m-d') . '.pdf');
    }
}