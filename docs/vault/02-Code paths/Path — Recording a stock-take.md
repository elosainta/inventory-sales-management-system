# Path — Recording a stock-take

Feature 4. What came in and what went out, counted off the Pantry shelves against live inventory.

**The sheet moves stock in both directions.** Recording it adds each line's In to the matching `inventory_items` row and takes its Out off — see step 6.

> [!warning] This note has been rewritten twice; the columns changed on 2026-08-26
> It began as a pure paper record — a digitised version of Sam's sheet, writing to nothing. Then the Out column was wired to inventory. It is now a two-way movement ledger:
>
> | | Old | New |
> |---|---|---|
> | Columns | Item, Prep date, Opening, In, Out, Closing | Item, Unit, **Current stock**, In, Out, **Balance** |
> | Opening / current stock | previous sheet's closing, carried forward, editable | read off `inventory_items.quantity_on_hand`, **not editable** |
> | In | recorded, moved nothing | **adds to stock** |
> | Out | deducts from stock | deducts from stock (unchanged) |
> | Closing / balance | typed by the chef | **computed**, and it is the new stock figure |
> | Prep date | a column | dropped |
>
> The DB columns were renamed to match (`opening` → `current_stock`, `closing` → `balance`) in `2026_08_26_000001_rename_stock_take_entry_columns`. Old rows keep their figures and still read correctly: an opening was the stock at the start and a closing the stock at the end.
>
> What still distinguishes this from [[Path — Recording a tally]] is *how* it writes: a stock-take **moves** stock by a delta, a tally **overwrites** the quantity with what was physically counted.

> [!note] One section, not two
> There were two — Pantry and Kitchen. Kitchen was retired on 2026-08-21: it was never
> used and no sheet was ever recorded against it, and its 64 catalog rows were deleted
> in 1.10.16. `StockTakeItem::SECTIONS` now holds `pantry` alone, and the section tab
> strip hides itself while there is only one choice. Everything below is still written
> per-section because the column and the constant remain.

## Trigger

| | |
|---|---|
| Route | `POST /stock-take` → `StockTakeController@store` |
| Gate | `record-stock-take` → `! isAdmin() && ! isOwner()` |
| Catalog | `StockTakeItemController` — CRUD, gate `manage-stock-take-items` → `isManager()` |
| Action | **none** — controller logic, see [[Layering rules]] |

Same owner exclusion as tally: chefs record, the Owner reviews.

## The sheet

| Item | Unit | Current stock | In | Out | Balance |
|---|---|---|---|---|---|

Current stock and Balance are **display only** — the form has no input for either. Only In and Out are typed, and only they are submitted.

Plus an **Open order** block below it (`stock_take_open_orders`) — things on order, noted while counting. They reach inventory when they arrive and someone enters them as In. Unrelated to the [[Path — Logging a sale]] "open order" flag, which is a staff meal. Same words, different features.

## The line

### 1 · `StockTakeController.php:33-36` — validate the section

```php
$section = request()->query('section', StockTakeItem::SECTION_PANTRY);
if (! array_key_exists($section, StockTakeItem::SECTIONS)) {
    $section = StockTakeItem::SECTION_PANTRY;
}
```

Whitelist against a constant, silently falling back rather than erroring. The section comes from a query string, so it is user input.

### 2 · `StockTakeController.php:41-45` — load the catalog with its stock

```php
$items = StockTakeItem::with('inventoryItem')
    ->where('section', $section)
    ->orderBy('sort_order')
    ->orderBy('name')
    ->get();
```

`sort_order` first so the catalog can be arranged to match the physical walk, with alphabetical as the tiebreak. The catalog is seeded from Sam's raw notes by `StockTakeItemSeeder` — idempotent `updateOrCreate` on `(section, name)`, so re-seeding never duplicates.

`with('inventoryItem')` is what replaced carry-forward. Current stock and the unit both come off the linked inventory row; the previous sheet is not consulted at all. Eager-loaded because the view reads it for all 54 rows.

### 3 · `StockTakeController.php:57-60` — what counts as a line

```php
->filter(fn ($row) => (float) ($row['qty_in'] ?? 0) > 0 || (float) ($row['qty_out'] ?? 0) > 0)
->filter(fn ($row) => filled($row['stock_take_item_id'] ?? null) || filled($row['item_name'] ?? null))
```

A line is kept once something actually moved. A row with no In and no Out is the shelf exactly as inventory already holds it — storing all 54 catalog rows to say so would bury the four that moved.

The second filter guarantees a resolvable name before the insert, since `item_name` is `NOT NULL`.

Open-order rows need only a name (line 62-64). Store bails if both collections are empty (66-68).

### 4 · `StockTakeController.php:72-75` — resolve names off the catalog

```php
$catalog = StockTakeItem::with('inventoryItem')
    ->whereIn('id', $entries->pluck('stock_take_item_id')->filter())
    ->get()
    ->keyBy('id');
```

Item name and unit are taken from here, never from the form. The form posts *which* item moved and *by how much*; everything else is the server's. A free-text row added with **+ Add another item** has no catalog id and falls back to its typed name — it is count-only by definition.

### 5 · `StockTakeController.php:77-113` — write

Stock moves first (step 6), then the sheet is written from the before/after figures it returns, then open orders. All inside one `DB::transaction()`, so a failed movement takes the sheet with it.

Every entry snapshots `item_name` and `unit` alongside the nullable `stock_take_item_id`, so history survives catalog edits and deletions.

### 6 · `StockTakeController.php:128-158` — move the stock

```php
$net = StockTakeEntry::netMovement($entries->map(fn ($row) => [
    'inventory_item_id' => $catalog->get($row['stock_take_item_id'] ?? null)?->inventory_item_id,
    'qty_in'            => $row['qty_in'] ?? 0,
    'qty_out'           => $row['qty_out'] ?? 0,
]));
```

Each catalog item carries a **nullable** `inventory_item_id`. Only linked items move stock; the catalog holds names the inventory has never heard of, and those stay count-only — 26 of the 54 Pantry items today. Links are set from the **Deducts from** picker on the catalog screen.

**The movement is netted per inventory item before anything is written.** Two sheet lines can point at the same stock; writing them one at a time would make the second overwrite the first instead of adding to it. The arithmetic lives on the model so it can be unit-tested without a database — `tests/Unit/StockTakeBalanceTest.php`.

```php
$before[$item->id] = (float) $item->quantity_on_hand;
$item->quantity_on_hand = StockTakeEntry::balance($before[$item->id], $net[$item->id]);
$item->last_updated     = now();
$item->save();
$after[$item->id] = (float) $item->quantity_on_hand;
```

Three things worth noticing:

- **`$before` is read here, not passed in from the form.** The delta applies to what stock holds *now*. A sale logged while the sheet sat open has already come off, and writing an absolute figure would put it back.
- **Clamped at zero** by `balance()`. An Out bigger than what is on hand means the figure inventory carried was already wrong.
- **Only the quantity is assigned.** `monetary_value` is derived in `InventoryItem::booted()` on every save ([[Inventory monetary value]]), and `unit_cost` is deliberately untouched — stock arriving here comes in at the cost inventory already carries. It is purchases and production, not counts, that reprice an item and recost its recipes.

`$before` and `$after` come back to `store()` so each line can record the stock it actually moved.

## The mathematics

- [[Stock-take movement]] — the balance identity, the netting, and why the clamp is the honest answer

$$\text{balance} = \max(0,\ \text{current} + \text{in} - \text{out})$$

> [!note] The identity is now enforced, and that is a change of policy
> The old sheet stored all four columns as typed and validated nothing — deliberately, because a gap between the arithmetic and the shelf was the Owner's signal. That reasoning does not survive current stock being read from the system instead of typed: there is no second opinion left to disagree with. Surfacing that gap is [[Path — Recording a tally]]'s job.

## What it touches

| Table | Effect |
|---|---|
| `stock_takes` | one row, audited |
| `stock_take_entries` | one per line that moved, not audited |
| `stock_take_open_orders` | one per noted item, not audited |
| `inventory_items` | `quantity_on_hand` moved by the net **In − Out**, per **linked** item; `last_updated` stamped; `monetary_value` re-derived; `unit_cost` untouched. Unlinked items: nothing |

## Gates

| Gate | Who | Why |
|---|---|---|
| `view-stock-take` | `! isAdmin()` | chefs and managers see history; admins have no kitchen access |
| `record-stock-take` | `! isAdmin() && ! isOwner()` | chefs record; the Owner reviews only |
| `manage-stock-take-items` | `isManager()` | catalog CRUD |

## Traps

- **Route order.** Catalog and `create` paths are declared before the `{stockTake}` wildcard.
- **Two "open orders".** This one is a to-order list; the sales one is a discounted staff meal.
- **Current stock and balance are not accepted from the form.** If you add an input for either, you have handed every junior chef a free write of any figure into `inventory_items`. `StoreStockTakeRequest` takes `qty_in` and `qty_out` and nothing else numeric — keep it that way.
- **A count-only line moves nothing**, and the show page marks it with a `count only` pill. Before that pill existed there was no way to tell from the sheet which lines reached inventory. Check **Deducts from** on the catalog screen if a count did not land.
- **A recorded sheet is not reversible.** There is no edit or delete path; correcting a mistake means a compensating line, or [[Path — Recording a tally]] to reconcile to a physical count.
- **A blank In and blank Out is not recorded at all.** The sheet is a movement log, not a snapshot — for a full picture of what is on the shelves, use a tally.

## See also

[[Stock-take movement]] · [[Path — Recording a tally]] · [[Glossary]] · [[Layering rules]]
