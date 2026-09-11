<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryItemRequest;
use App\Models\InventoryItem;
use App\Models\MarketPurchaseLine;
use App\Models\ProductionBatchLine;
use App\Models\PurchaseLine;
use App\Models\RecipeIngredient;
use App\Models\Supplier;
use App\Models\WastageEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class InventoryItemController extends Controller
{
    public function index()
    {
        Gate::authorize('view-inventory');

        $query = InventoryItem::query();

        if (request('search')) {
            $query->where('name', 'like', '%' . request('search') . '%');
        }

        if (request('category') && request('category') !== 'all') {
            $query->where('category', request('category'));
        }

        $items = $query->orderBy('name')->get();
        $totalValue = $items->sum('monetary_value');

        $categories = InventoryItem::CATEGORIES;

        // Only for managers, and only to fill one dropdown in the edit modal.
        // Cached an hour by the client, and empty when Bukku is not configured
        // — the field then simply does not render.
        $bukkuProducts = Gate::allows('manage-inventory') ? \App\Support\Bukku::products() : [];

        return view('inventory.index', compact('items', 'totalValue', 'categories', 'bukkuProducts'));
    }

    public function store(StoreInventoryItemRequest $request)
    {
        Gate::authorize('record-inventory');

        $data = $request->validated();
        $data['last_updated'] = now();

        $item = InventoryItem::create($data);

        // The purchase and market-purchase forms create an ingredient inline,
        // from the line-item picker, when what was typed is not on the shelf
        // yet. They need the new item back to put it in the picker — a redirect
        // would throw away the half-filled purchase they are standing in.
        if ($request->expectsJson()) {
            return response()->json([
                'id'   => (int) $item->id,
                'name' => $item->name,
                'unit' => $item->unit,
                'cost' => (float) $item->unit_cost,
            ], 201);
        }

        return back()->with('success', 'Ingredient added.')->withFragment('item-' . $item->id);
    }

    public function update(StoreInventoryItemRequest $request, InventoryItem $inventoryItem)
    {
        Gate::authorize('record-inventory');

        $data = $request->validated();

        // A part timer holds record-inventory but not manage-inventory: they
        // key a count in, they do not own the catalogue. Everything but the
        // quantity is dropped here rather than merely hidden in the form —
        // the modal is not the security boundary, this line is. Without it,
        // a hand-rolled POST would rename an ingredient or rewrite its cost,
        // and unit_cost feeds every recipe's plate cost.
        if (! Gate::allows('manage-inventory')) {
            $data = array_intersect_key($data, ['quantity_on_hand' => true]);
        }

        $data['last_updated'] = now();

        $inventoryItem->update($data);

        return back()->with('success', 'Ingredient updated.')->withFragment('item-' . $inventoryItem->id);
    }

    public function destroy(InventoryItem $inventoryItem)
    {
        Gate::authorize('manage-inventory');

        // Every table below holds a foreign key onto inventory_items. Three of
        // them restrict the delete, so letting it through throws a raw 500 at
        // whoever pressed Remove; the other two cascade, so the delete would
        // succeed and quietly take the purchase and production history with it.
        // Neither is what Remove is meant to do, so refuse and name the reason.
        $usedBy = collect([
            'recipe'           => RecipeIngredient::class,
            'purchase'         => PurchaseLine::class,
            'wastage entry'    => WastageEntry::class,
            'market purchase'  => MarketPurchaseLine::class,
            'production batch' => ProductionBatchLine::class,
        ])->map(fn (string $model) => $model::where('inventory_item_id', $inventoryItem->id)->count())
            ->filter();

        if ($usedBy->isNotEmpty()) {
            $parts = $usedBy->map(fn (int $count, string $noun) => $count . ' ' . Str::plural($noun, $count))->values();

            return back()->with('error', $inventoryItem->name . ' is still used by ' . $parts->join(', ', ' and ') . '. Remove it from those first.');
        }

        $inventoryItem->delete();

        return back()->with('success', 'Ingredient removed.');
    }

    public function exportPdf()
    {
        Gate::authorize('export-pdf');

        // Stock isn't linked to a supplier - it's linked to a purchase, which is
        // linked to a supplier. So "who do we buy this from" is derived as
        // whoever the item was most recently bought from; an item never bought
        // through a supplier order (market-purchase-only, or produced) falls
        // into a trailing "No supplier" page rather than being dropped.
        $latestSupplierIdByItem = PurchaseLine::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_lines.purchase_id')
            ->whereNotNull('purchase_lines.inventory_item_id')
            ->orderByDesc('purchases.purchase_date')
            ->orderByDesc('purchase_lines.id')
            ->get(['purchase_lines.inventory_item_id', 'purchases.supplier_id'])
            ->unique('inventory_item_id')
            ->pluck('supplier_id', 'inventory_item_id');

        $supplierNames = Supplier::pluck('name', 'id');

        $items = InventoryItem::orderBy('name')->get();
        $totalValue = $items->sum('monetary_value');

        $suppliers = $items->groupBy(fn ($item) => $latestSupplierIdByItem->get($item->id))
            ->map(fn ($group, $supplierId) => [
                'supplier' => $supplierId ? $supplierNames->get($supplierId, 'Unknown supplier') : null,
                'items'    => $group,
            ])
            ->sortBy(fn ($group) => ($group['supplier'] ? '0' : '1') . $group['supplier'])
            ->values();

        $pdf = Pdf::loadView('pdfs.inventory', compact('suppliers', 'totalValue'));
        $filename = 'inventory-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }
}