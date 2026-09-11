# Path — Recording a tally

Feature 5. A person walks the shelves, enters what they physically counted against the live inventory (254 items as at 2026-09-03), and **the count wins** — live stock is overwritten to match.

The only path outside `app/Domain/` that rewrites live stock quantities.

## Trigger

| | |
|---|---|
| Route | `POST /tally` → `InventoryTallyController@store`, throttled `20,1` |
| Gate | `record-tally` → `! isAdmin() && ! isOwner()` |
| Form Request | `app/Http/Requests/StoreInventoryTallyRequest.php` |
| Action | **none** — logic is in the controller. See [[Layering rules]] |

> [!note] The Owner cannot record a tally
> `record-tally` excludes owners on purpose. Whoever counts is not whoever reviews the variance — the separation is the control. The Owner gets `view-tally` and reads the variance report. [[Roles]].

## The line

### 1 · `InventoryTallyController.php:26-40` — the count sheet

```php
$items = InventoryItem::orderBy('category')
    ->orderBy('name')
    ->get()
    ->groupBy(fn ($item) => $item->category ?: 'Uncategorised');
```

Grouped by category so the form follows the physical layout of the shelves. `?: 'Uncategorised'` catches both null and empty string.

### 2 · `InventoryTallyController.php:48-51` — blank means "not counted"

```php
$lines = collect($request->validated('lines', []))
    ->filter(fn ($row) => isset($row['counted_quantity']) && $row['counted_quantity'] !== '')
    ->values();
```

> [!danger] Blank ≠ zero
> This distinction is the whole feature. A blank field means *"I did not count this"* — the item is skipped and its stock is untouched. A typed `0` means *"there are none left"* and **sets stock to zero**. Treating blank as zero would wipe the entire inventory the first time someone counted three items.
>
> Note `!== ''` rather than `empty()` — `empty('0')` is `true` in PHP, so `empty()` would silently discard every legitimate zero count.

If nothing was counted, `store` bails with an error rather than writing an empty sheet (lines 53-55).

### 3 · `InventoryTallyController.php:59` — snapshot before touching anything

```php
$items = InventoryItem::whereIn('id', $lines->pluck('inventory_item_id')->filter())->get()->keyBy('id');
```

Loaded **before** the transaction body writes. These in-memory models hold the pre-reconcile figures, which is what makes the recorded variance honest.

### 4 · `InventoryTallyController.php:61-98` — write the line, then reconcile

```php
$tally->lines()->create([
    'inventory_item_id' => $item?->id,
    'item_name'         => $item?->name ?? ($row['item_name'] ?? 'Unknown item'),
    'unit'              => $item?->unit ?? ($row['unit'] ?? null),
    'category'          => $item?->category ?? ($row['category'] ?? null),
    'system_quantity'   => $item?->quantity_on_hand,   // BEFORE the write below
    'counted_quantity'  => $row['counted_quantity'],
]);
```

Four values are **snapshotted**: name, unit, category, and the system quantity. Renaming or recategorising an item later does not rewrite history, and the recorded variance faithfully captures the discrepancy that was found on the day.

Then, still inside the loop:

```php
$item->quantity_on_hand = $row['counted_quantity'];
$item->monetary_value   = round((float) $row['counted_quantity'] * (float) $item->unit_cost, 2);
$item->last_updated     = now();
```

**Assignment, not adjustment.** Contrast [[Path — Logging a purchase]], which uses `+=`.

> [!note] `unit_cost` is deliberately untouched
> Counting tells you *how many* are on the shelf, not *what they cost*. Leaving `unit_cost` alone means [[Plate cost]] is unaffected and no recipe recosting cascade is needed — the one write to `inventory_items` that does **not** trigger the cascade of [[Path — Logging a purchase]].

## The mathematics

$$v = q_{\text{counted}} - q_{\text{system}}$$

`app/Models/InventoryTallyLine.php:40-47`. Full interpretation, sign conventions and the review view: [[Tally variance]].

Also [[Inventory monetary value]], recomputed at the counted quantity.

## The review view

`inventory-tally.show` renders per line:

| Variance | Meaning | Colour |
|---|---|---|
| negative | short — shrinkage, waste, theft, unrecorded use | red |
| positive | over — under-recorded production or over-delivery | copper |
| zero | match | green |

Plus counts of items counted and items differing. This is the Owner's screen.

## What it touches

| Table | Effect |
|---|---|
| `inventory_tallies` | one row, audited |
| `inventory_tally_lines` | one per counted item, **not** audited (child detail) |
| `inventory_items` | `quantity_on_hand` and `monetary_value` overwritten per counted item |
| `audits` | one for the tally, one per reconciled item |

## Traps

- **Irreversible.** Reconciliation overwrites the previous quantity with no undo. The old value survives only in the `audits` before/after payload and in `system_quantity` on the tally line.
- **Route order.** `/tally/create` must be declared before `/tally/{inventoryTally}` or `create` binds as a model id.
- **`InventoryTallyLine` has `$timestamps = false`** — it inherits the parent tally's `counted_on`.
- **Not to be confused with stock-take.** Both write to stock, but differently: [[Path — Recording a stock-take]] *moves* stock by its In and Out columns, while a tally *reconciles* — it overwrites the quantity with what was physically counted. [[Glossary]] has the side-by-side.

## See also

[[Tally variance]] · [[Path — Recording a stock-take]] · [[Inventory as shared state]] · [[Layering rules]]
