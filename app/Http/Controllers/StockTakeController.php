<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockTakeRequest;
use App\Models\InventoryItem;
use App\Models\StockTake;
use App\Models\StockTakeEntry;
use App\Models\StockTakeItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class StockTakeController extends Controller
{
    public function index()
    {
        Gate::authorize('view-stock-take');

        $stockTakes = StockTake::with('counter')
            ->withCount('entries')
            ->orderByDesc('taken_on')
            ->orderByDesc('id')
            ->paginate(25);

        return view('stock-take.index', compact('stockTakes'));
    }

    public function create()
    {
        Gate::authorize('record-stock-take');

        $section = request()->query('section', StockTakeItem::SECTION_PANTRY);
        if (! array_key_exists($section, StockTakeItem::SECTIONS)) {
            $section = StockTakeItem::SECTION_PANTRY;
        }

        // Current stock is read off live inventory, not carried forward from the
        // previous sheet — the figure the kitchen works against is the one
        // inventory holds right now, and every other screen already moves it.
        $items = StockTakeItem::with('inventoryItem')
            ->where('section', $section)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // The whole inventory, so a chef can put something on today's sheet that
        // nobody thought to add to the catalog. Without this the only way to
        // count an item was to find a manager, have them edit the catalog on
        // another page, and start the sheet again.
        $inventoryItems = InventoryItem::orderBy('name')
            ->get(['id', 'name', 'unit', 'unit_cost', 'quantity_on_hand']);

        return view('stock-take.create', compact('section', 'items', 'inventoryItems'));
    }

    public function store(StoreStockTakeRequest $request)
    {
        Gate::authorize('record-stock-take');

        // A line is worth recording once something actually moved. A row with
        // no In and no Out is the shelf exactly as inventory already has it, and
        // a catalog of 54 items would otherwise store 54 rows saying nothing.
        $entries = collect($request->validated('entries', []))
            ->filter(fn ($row) => (float) ($row['qty_in'] ?? 0) > 0 || (float) ($row['qty_out'] ?? 0) > 0)
            ->filter(fn ($row) => filled($row['stock_take_item_id'] ?? null)
                || filled($row['inventory_item_id'] ?? null)
                || filled($row['item_name'] ?? null))
            ->values();

        $entries = $this->adoptPickedInventory($entries, $request->validated('section'));

        $openOrders = collect($request->validated('open_orders', []))
            ->filter(fn ($row) => filled($row['item_name'] ?? null))
            ->values();

        if ($entries->isEmpty() && $openOrders->isEmpty()) {
            return back()->withInput()->with('error', 'Enter an In or Out figure for at least one item before saving.');
        }

        // Names and units come off the catalog, never off the form — the form
        // only submits which item moved and by how much.
        $catalog = StockTakeItem::with('inventoryItem')
            ->whereIn('id', $entries->pluck('stock_take_item_id')->filter())
            ->get()
            ->keyBy('id');

        $stockTake = DB::transaction(function () use ($request, $entries, $openOrders, $catalog) {
            $stockTake = StockTake::create([
                'section'    => $request->validated('section'),
                'taken_on'   => $request->validated('taken_on'),
                'counted_by' => auth()->id(),
                'note'       => $request->validated('note'),
            ]);

            [$before, $after] = $this->applyMovements($entries, $catalog);

            foreach ($entries as $row) {
                $item        = $catalog->get($row['stock_take_item_id'] ?? null);
                $inventoryId = $item?->inventory_item_id;

                $stockTake->entries()->create([
                    'stock_take_item_id' => $item?->id,
                    'item_name'          => $item?->name ?? $row['item_name'],
                    'unit'               => $item?->inventoryItem?->unit ?? $item?->default_unit ?? ($row['unit'] ?? null),
                    // Null on a count-only line: with no inventory behind it
                    // there is no stock figure to show and none to work out.
                    'current_stock'      => $inventoryId ? ($before[$inventoryId] ?? null) : null,
                    'qty_in'             => $row['qty_in'] ?? null,
                    'qty_out'            => $row['qty_out'] ?? null,
                    'balance'            => $inventoryId ? ($after[$inventoryId] ?? null) : null,
                ]);
            }

            foreach ($openOrders as $row) {
                $stockTake->openOrders()->create([
                    'item_name' => $row['item_name'],
                    'quantity'  => $row['quantity'] ?? null,
                    'note'      => $row['note'] ?? null,
                ]);
            }

            return $stockTake;
        });

        return redirect()->route('stock-take.show', $stockTake)->with('success', 'Stock-take recorded — inventory updated.');
    }

    /**
     * Turn rows a chef added straight from inventory into catalog lines, so the
     * rest of store() sees them as ordinary sheet rows and they move stock.
     *
     * The item is added to the catalog rather than recorded loose on this one
     * sheet: something counted once is almost always counted again, and this
     * way it is simply on tomorrow's sheet for everybody. `updateOrCreate` on
     * section+name matches the table's unique key and the seeder's convention,
     * so counting the same thing twice links one row rather than making two.
     *
     * Catalog CRUD is manager-only (`manage-stock-take-items`) and this is not
     * a way around that: the name and unit come from an inventory item the
     * chef picked, nothing can be renamed, relinked or deleted, and the create
     * is audited through LogsActivity under the chef's own name.
     */
    private function adoptPickedInventory(Collection $entries, string $section): Collection
    {
        $picked = $entries->pluck('inventory_item_id')->filter()->unique();

        if ($picked->isEmpty()) {
            return $entries;
        }

        $catalogByInventoryId = InventoryItem::whereIn('id', $picked)->get()
            ->mapWithKeys(fn ($inventory) => [
                $inventory->id => StockTakeItem::updateOrCreate(
                    ['section' => $section, 'name' => $inventory->name],
                    ['inventory_item_id' => $inventory->id, 'default_unit' => $inventory->unit],
                )->id,
            ]);

        return $entries->map(function ($row) use ($catalogByInventoryId) {
            // A row that already names a catalog line keeps it — the picker is
            // only ever the source for rows the sheet did not already carry.
            if (filled($row['stock_take_item_id'] ?? null)) {
                return $row;
            }

            $row['stock_take_item_id'] = $catalogByInventoryId->get($row['inventory_item_id'] ?? null);

            return $row;
        });
    }

    /**
     * Move live stock by what the sheet says came in and went out, and hand back
     * the figure each inventory item held before and after, so every line can
     * record the stock it actually moved.
     *
     * Only catalog items that name an inventory item move anything — the catalog
     * carries names the inventory has never heard of, and those stay count-only.
     *
     * @return array{0: array<int, float>, 1: array<int, float>}
     */
    private function applyMovements(Collection $entries, Collection $catalog): array
    {
        // Resolve each line to the stock it moves, then net the movement per
        // inventory item before writing anything — see StockTakeEntry::netMovement.
        $net = StockTakeEntry::netMovement($entries->map(fn ($row) => [
            'inventory_item_id' => $catalog->get($row['stock_take_item_id'] ?? null)?->inventory_item_id,
            'qty_in'            => $row['qty_in'] ?? 0,
            'qty_out'           => $row['qty_out'] ?? 0,
        ]));

        $before = $after = [];

        // The movement is applied to what live stock holds now, not to the figure
        // the form was rendered with — a sale logged while the sheet was open has
        // already come off, and writing an absolute figure would undo it.
        // monetary_value is derived in InventoryItem::booted(), so assigning the
        // quantity is all that is needed. unit_cost is untouched: stock arriving
        // here comes in at the cost inventory already carries, and it is
        // purchases, not counts, that reprice an item and recost its recipes.
        foreach (InventoryItem::whereIn('id', array_keys($net))->get() as $item) {
            $before[$item->id] = (float) $item->quantity_on_hand;

            $item->quantity_on_hand = StockTakeEntry::balance($before[$item->id], $net[$item->id]);
            $item->last_updated     = now();
            $item->save();

            $after[$item->id] = (float) $item->quantity_on_hand;
        }

        return [$before, $after];
    }

    public function show(StockTake $stockTake)
    {
        Gate::authorize('view-stock-take');

        $stockTake->load(['counter', 'entries', 'openOrders']);

        return view('stock-take.show', compact('stockTake'));
    }
}
