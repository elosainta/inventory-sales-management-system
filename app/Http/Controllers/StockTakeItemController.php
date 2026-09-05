<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockTakeItemRequest;
use App\Http\Requests\UpdateStockTakeItemRequest;
use App\Models\InventoryItem;
use App\Models\StockTakeItem;
use Illuminate\Support\Facades\Gate;

class StockTakeItemController extends Controller
{
    public function index()
    {
        Gate::authorize('manage-stock-take-items');

        $sections = collect(StockTakeItem::SECTIONS)->mapWithKeys(fn ($label, $key) => [
            $key => StockTakeItem::with('inventoryItem')->where('section', $key)->orderBy('sort_order')->orderBy('name')->get(),
        ]);

        $inventoryItems = InventoryItem::orderBy('name')->get();

        return view('stock-take.items', compact('sections', 'inventoryItems'));
    }

    public function store(StoreStockTakeItemRequest $request)
    {
        Gate::authorize('manage-stock-take-items');

        $section = $request->validated('section');
        $nextOrder = (int) StockTakeItem::where('section', $section)->max('sort_order') + 1;

        StockTakeItem::create([
            'section'      => $section,
            'name'         => $request->validated('name'),
            'default_unit' => $request->validated('default_unit'),
            'inventory_item_id' => $request->validated('inventory_item_id'),
            'sort_order'   => $nextOrder,
        ]);

        return back()->with('success', 'Item added.');
    }

    public function update(UpdateStockTakeItemRequest $request, StockTakeItem $stockTakeItem)
    {
        Gate::authorize('manage-stock-take-items');

        $stockTakeItem->update([
            'name'         => $request->validated('name'),
            'default_unit' => $request->validated('default_unit'),
            'inventory_item_id' => $request->validated('inventory_item_id'),
        ]);

        return back()->with('success', 'Item updated.');
    }

    public function destroy(StockTakeItem $stockTakeItem)
    {
        Gate::authorize('manage-stock-take-items');

        $stockTakeItem->delete();

        return back()->with('success', 'Item removed.');
    }
}
