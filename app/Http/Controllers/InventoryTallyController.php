<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryTallyRequest;
use App\Models\InventoryItem;
use App\Models\InventoryTally;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class InventoryTallyController extends Controller
{
    public function index()
    {
        Gate::authorize('view-tally');

        $tallies = InventoryTally::with('counter')
            ->withCount('lines')
            ->orderByDesc('counted_on')
            ->orderByDesc('id')
            ->paginate(25);

        return view('inventory-tally.index', compact('tallies'));
    }

    public function create()
    {
        Gate::authorize('record-tally');

        // The whole live inventory, ordered the way the shelves run, for the
        // sheet's search box and its category buttons. Flat, not grouped: the
        // page no longer prints a card per category — it starts empty and the
        // chef puts on what they counted. Recording the tally reconciles each
        // counted item's live stock to the figure entered (see store()).
        $items = InventoryItem::orderBy('category')
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'category', 'quantity_on_hand']);

        return view('inventory-tally.create', compact('items'));
    }

    public function store(StoreInventoryTallyRequest $request)
    {
        Gate::authorize('record-tally');

        // Keep only the items the chef actually counted — a blank field means
        // "not counted", exactly like leaving a line untouched on the paper sheet.
        $lines = collect($request->validated('lines', []))
            ->filter(fn ($row) => isset($row['counted_quantity']) && $row['counted_quantity'] !== '')
            ->values();

        if ($lines->isEmpty()) {
            return back()->withInput()->with('error', 'Enter a counted quantity for at least one item before saving.');
        }

        // Snapshot each item's current system figure at the moment of recording,
        // so the variance is faithful even if live stock changes later.
        $items = InventoryItem::whereIn('id', $lines->pluck('inventory_item_id')->filter())->get()->keyBy('id');

        $tally = DB::transaction(function () use ($request, $lines, $items) {
            $tally = InventoryTally::create([
                'counted_by' => auth()->id(),
                'counted_on' => $request->validated('counted_on'),
                'note'       => $request->validated('note'),
            ]);

            foreach ($lines as $row) {
                $item = $items->get($row['inventory_item_id'] ?? null);

                $tally->lines()->create([
                    'inventory_item_id' => $item?->id,
                    'item_name'         => $item?->name ?? ($row['item_name'] ?? 'Unknown item'),
                    'unit'              => $item?->unit ?? ($row['unit'] ?? null),
                    'category'          => $item?->category ?? ($row['category'] ?? null),
                    // Snapshot the system figure BEFORE reconciling, so the recorded
                    // variance (counted − system) reflects the discrepancy we found.
                    'system_quantity'   => $item?->quantity_on_hand,
                    'counted_quantity'  => $row['counted_quantity'],
                ]);

                // Reconcile live stock to the physical count — the shelf is the
                // source of truth. Setting quantity_on_hand is enough — the model
                // derives monetary_value from it. unit_cost is untouched, so recipe
                // plate costs are unaffected and need no recalculation.
                if ($item) {
                    $item->quantity_on_hand = $row['counted_quantity'];
                    $item->last_updated     = now();
                    $item->save();
                }
            }

            return $tally;
        });

        return redirect()->route('tally.show', $tally)->with('success', 'Tally check recorded — live stock reconciled to your counts.');
    }

    public function show(InventoryTally $inventoryTally)
    {
        Gate::authorize('view-tally');

        $inventoryTally->load(['counter', 'lines']);

        return view('inventory-tally.show', ['tally' => $inventoryTally]);
    }
}
