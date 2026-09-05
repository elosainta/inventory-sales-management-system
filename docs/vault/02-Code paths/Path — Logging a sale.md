# Path — Logging a sale

The most connected write in the system. One form submission records revenue, takes the dish off stock, stores photos, and writes several audit rows.

## Trigger

| | |
|---|---|
| Route | `POST /sales` → `SaleController@store` |
| Gate | `manage-sales` → `isManager()` (Owner + Head Chef) |
| Form Request | `app/Http/Requests/StoreSaleRequest.php` |
| Action | `app/Domain/Sales/Actions/LogSale.php` |
| Encoding | `multipart/form-data` — the modal accepts `photos[]` |

## The line

### 1 · `SaleController@store`

Authorizes, then delegates. Photos are handled here rather than in the action because they are presentation-layer detail, not business logic.

### 2 · `LogSale.php:13-19` — compute revenue before anything else

```php
$data['total_revenue'] = Sale::revenue(
    $data['qty_sold'],
    $data['selling_price'],
    (float) ($data['discount'] ?? 0)
);
```

> [!note] The cast is not decorative
> A blank discount field arrives as `''`, not `null`, unless `ConvertEmptyStringsToNull` is in the stack. `''` into an `int|float` parameter is a `TypeError` and a 500. `?? 0` alone does not save you — `''` is not null. The `(float)` does.

The formula itself lives in **one place** — `Sale::revenue()` at `app/Models/Sale.php:31-34` — and is called by both this action and `SaleController@update`. See [[Sale revenue]] for why that single-source rule exists.

### 3 · `LogSale.php:21` — open the transaction

```php
return DB::transaction(function () use ($data) {
```

Everything below either all happens or none of it does. A sale recorded without its stock deduction is silent corruption — nobody notices until the next count.

### 4 · `LogSale.php:22` — write the sale

`Sale::create($data)` fires the `created` model event → an `audits` row appears with the full `after` payload. Nothing calls the audit explicitly. [[Audit trail]].

### 5 · `LogSale.php:24` — load the recipe with ingredients eager

```php
$recipe = Recipe::with('ingredients.inventoryItem')->find($data['recipe_id']);
```

Two levels of eager loading to avoid an N+1 inside the loop below.

### 6 · `LogSale.php:28-56` — deduct stock, down one of two paths

**Which stock a sale takes depends on whether the dish is made before it is sold.** See [[Path — Logging production]] for the pipeline this belongs to.

```php
if ($output = $recipe->outputInventoryItem) {
    $output->quantity_on_hand = max(0, $output->quantity_on_hand - $data['qty_sold']);
    $output->save();
} else {
    foreach ($recipe->ingredients as $ingredient) { /* ... */ }
}
```

**Finished-dish path** — the recipe names an `output_inventory_item_id`, so production has already taken the raw ingredients off the shelf and turned them into finished dishes. The sale takes the finished dish and nothing else:

$$\text{new}_{\text{dish}} = \max(0,\; h_{\text{dish}} - n)$$

**Raw-ingredient path** — the recipe has no finished dish, so the sale is still the only thing that moves stock and deducts the ingredients itself, exactly as it always did:

$$\text{deduct}_i = q_i \times n \qquad\text{new}_i = \max(0,\; h_i - \text{deduct}_i)$$

where $q_i$ is the recipe quantity of ingredient $i$, $n$ the number of plates sold, $h_i$ the quantity on hand.

> [!danger] The branch is the whole point — never collapse it
> Running both would take every ingredient off stock twice: once when the dish was made, once when it was sold. Every recipe is on the raw-ingredient path until someone gives it a finished dish on the Recipes page, which is what made the pipeline safe to ship against live data.

The `max(0, …)` clamp means overselling silently floors stock at zero rather than going negative. That is a deliberate choice: negative stock would poison [[Inventory monetary value]] and every dashboard figure downstream. The cost is that oversells become invisible — the discrepancy only surfaces at the next [[Path — Recording a tally]].

`unit_cost` is **not** touched, so no recipe needs recosting. Contrast with [[Path — Logging a purchase]], which does change it.

### 7 · Photos

Streamed to the **private** disk under `sale-attachments/`. Images only (jpg, png, webp, gif, heic), 25 MB each. `SaleAttachment` is child detail and deliberately **not** audited. Downloads go through the `sales.attachment` route behind the `view-sales` gate.

Photos are attached on create only — editing a sale does not touch them.

## The mathematics

- [[Sale revenue]] — gross less discount, clamped at zero
- [[Inventory monetary value]] — refreshed for every ingredient touched
- Feeds [[Cost of goods sold]] and therefore [[Gross margin]]

## Open orders

An **option on this form**, not a separate module. `is_open_order` (bool) + `discount` on `sales`, added by `2026_07_30_000001_add_open_order_to_sales_table.php`.

It marks food a staff member ordered from the kitchen, usually discounted. Because it is an ordinary sale it inherits stock deduction, photos, audit, dashboards and the PDF for free.

**Recorded anonymously.** There is no staff column and no name is shown. The `LogsActivity` trail already records who logged it if the Owner needs to trace one.

> [!danger] The hidden input is required for edit to work
> ```html
> <input type="hidden" name="is_open_order" value="0">
> <input type="checkbox" name="is_open_order" value="1">
> ```
> An unchecked checkbox sends **nothing**. Without the paired hidden field, unticking the box on the edit form leaves the flag set forever.

`discount` is not gated to open orders — a normal sale can carry one too.

## What it touches

| Table | Effect |
|---|---|
| `sales` | one row |
| `sale_attachments` | zero or more rows |
| `inventory_items` | one update per recipe ingredient |
| `audits` | one row for the sale, one per inventory item touched |

## Traps

- **No reversal on edit.** `SaleController@update` recalculates `total_revenue` but never un-deducts stock. Edits are for typos. A genuine cancellation has to be corrected by a [[Path — Recording a tally]].
- **`recipe_id` is required.** Genuinely off-menu revenue cannot be recorded at all — an open order is still a kitchen dish.
- **Low-stock alerts do not fire here.** Selling can drive an item below its threshold in silence. The alert is raised only by [[Path — Logging production]]. See [[Low stock threshold]].

## See also

[[Path — Logging a purchase]] · [[Path — Recording a tally]] · [[Inventory as shared state]] · [[Sale revenue]]
