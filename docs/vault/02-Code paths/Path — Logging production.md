# Path — Logging production

The middle of **Inventory → Production → Sales**. The chef says which dish and how many they made; the recipe is the formula that decides what came off the shelf. Raw stock goes down, the finished dish goes up.

It also has one thing no other path has: **this is the only place the low-stock alert is raised.**

> [!warning] Rewritten on 2026-08-26 — this used to be a second Purchases
> Production was a list of inventory items with a quantity and a unit cost typed in per line. It only ever **added** to stock, and it had no idea what a recipe was. Nobody used it: **zero batches were ever logged.**
>
> | | Old | New |
> |---|---|---|
> | Input | inventory items, one line at a time | a quantity against each dish cooked |
> | Ingredients | not modelled | consumed, per the recipe formula |
> | Effect on stock | added the produced item | **deducts raw, adds the finished dish** |
> | Cost | typed per line | `plate_cost × qty` |
> | `production_batch_lines` | items produced | **items consumed** |
>
> The rows kept their shape, so the table did not change — only what a row means.

## The pipeline switch

`recipes.output_inventory_item_id` names the finished stock a batch creates. It is **nullable on purpose**, and that is the whole safety design:

| `output_inventory_item_id` | Production | Sale |
|---|---|---|
| **set** | consumes ingredients, adds finished dishes | deducts **the finished dish** |
| **null** | consumes ingredients, adds nothing | deducts **the raw ingredients** (unchanged) |

A recipe on the null path behaves exactly as it did before this existed, so shipping the pipeline changed no live behaviour on day one. Recipes get wired up one at a time from the **Finished dish** picker on the Recipes page.

> [!danger] The two paths must never both run for one recipe
> If a sale deducted raw ingredients *and* production had already deducted them, every dish would take its ingredients off stock twice and the kitchen would read as far emptier than it is. That is the entire reason `LogSale` branches instead of always deducting. Do not "simplify" that branch away.

## Trigger

| | |
|---|---|
| Route | `POST /production` → `ProductionController@store` |
| Gate | `manage-production` → `! isAdmin()` — chefs log what they cook |
| Action | `app/Domain/Production/Actions/LogProduction.php` |

Junior chefs log production; only `delete-entries` removes a batch.

## The line

### 0 · `ProductionController@store` — one submission, many dishes

A chef records everything they cooked in one go, so the form posts a quantity
per dish — `quantities[recipe_id] => qty`, most of them blank:

```php
foreach ($data['quantities'] as $recipeId => $quantity) {
    $action->execute($shared + [
        'recipe_id'         => (int) $recipeId,
        'quantity_produced' => $quantity,
    ]);
}
```

**Each dish becomes its own batch**, sharing `produced_by`, `production_date`
and `notes`. That keeps `total_value` costed at exactly one recipe's plate
cost, and lets one dish be deleted later without unpicking the others. The loop
runs inside a single `DB::transaction` so a failure on the fourth dish does not
leave the first three written to inventory.

> [!warning] Array keys arrive from the request
> `quantities` is keyed by recipe id, and nothing validates an array *key* by
> default. `StoreProductionBatchRequest::prepareForValidation()` strips the
> blank rows and re-exposes the surviving keys as a plain `recipe_ids` list, so
> `exists:recipes,id` actually runs against them. Without that step a tampered
> key would reach `findOrFail()` inside the transaction.

### 1 · `LogProduction.php:26-29` — the dish and the count

```php
$recipe = Recipe::with('ingredients.inventoryItem', 'outputInventoryItem')
    ->findOrFail($data['recipe_id']);

$quantity = (float) $data['quantity_produced'];
```

Those two values are **all the action needs**. `StoreProductionBatchRequest`
accepts `quantities`, `produced_by`, `production_date` and `notes` — nothing
about ingredients. A posted ingredient list would be a free write of any
quantity into `inventory_items` by anyone holding `manage-production`, which is
every chef.

### 2 · `LogProduction.php:35` — batch value

```php
$data['total_value'] = round((float) $recipe->plate_cost * $quantity, 2);
```

$$\text{batch value} = \operatorname{round}(\text{plate cost} \times q,\ 2)$$

`plate_cost` is the recipe's ingredient cost with the misc overhead already applied ([[Plate cost]]), so production is costed the same way the dashboard costs a sale.

### 3 · `LogProduction.php:40` — the formula

```php
$consumed = $recipe->consumptionFor($quantity);
```

Returns `[inventory_item_id => quantity]`. **Summed per item, not per ingredient row** — a recipe can list the same inventory item twice (oil used in two steps), and deducting those one at a time would apply only the last, leaving the shelf holding stock the kitchen has already used. Unit-tested in `tests/Unit/RecipeConsumptionTest.php`; rounded to four places because recipe quantities carry four while stock columns carry two.

### 4 · `LogProduction.php:45-68` — take it off the shelf

```php
$item->quantity_on_hand = max(0, (float) $item->quantity_on_hand - $used);
```

One `production_batch_lines` row per ingredient consumed, at the item's current `unit_cost`, so the batch keeps a faithful record of what it ate and at what price.

Clamped at zero. Only the quantity is assigned — `monetary_value` is derived in `InventoryItem::booted()` ([[Inventory monetary value]]). **`unit_cost` is deliberately untouched:** cooking with an ingredient does not change what it cost to buy. This is the sharpest break from the old behaviour, which overwrote `unit_cost` on every line and could reprice an ingredient everywhere from one typo.

### 5 · `LogProduction.php:73-84` — the finished dish becomes stock

```php
$output->quantity_on_hand = (float) $output->quantity_on_hand + $quantity;
$output->unit_cost        = $recipe->plate_cost;
```

Costed at what it took to make, so a sale of it reports a truthful margin. The output item's `unit_cost` has just moved, so any recipe using it **as an ingredient** — a dish built on a prepped component — is recosted by the same `whereHas` cascade [[Path — Logging a purchase]] uses.

A recipe with no output item still consumes its ingredients. It simply has nowhere to put the result yet.

### 6 · `LogProduction.php:64-66` — collect low-stock items

```php
if ($item->isLowStock()) {
    $lowStockItems[] = $item;
}
```

Checked **after** the deduction, and it now reads the right way round: a batch that empties the shelf raises the alert. Under the old code the check ran after an *addition*, so it meant "you made some and it is still not enough."

Threshold logic and why it is half: [[Low stock threshold]].

### 7 · `LogProduction.php:91-115` — notify, scoped to the actor's world

```php
$actorIsDemo = (bool) auth()->user()?->is_demo;

$headChefs = User::where('role', User::ROLE_HEAD_CHEF)
    ->where('is_demo', $actorIsDemo)
    ->get();
```

> [!danger] The `is_demo` filter is a real bug fix — commit `feafeb5`
> Without it, a trainee clicking around the demo sandbox sends live low-stock alerts to the real Head Chef. The filter keeps the alert inside the actor's own world: demo action → demo head chef, real action → real head chefs. Scheduled and CLI runs have no actor, so `?->` yields `null`, `(bool) null` is `false`, and they fall through to the real head chefs. That is the correct default. See [[Demo sandbox]].

The `notify()` call is wrapped in a `try`/`catch` that only logs. It runs inside the transaction that just saved the batch, and Resend can refuse a send outright — an uncaught throw would roll the batch back and lose work a chef had already logged because an email failed. The batch is the record that matters; the alert is not.

The alert is a **database** notification (no queue worker required) and renders as a red banner in the sidebar. Managers dismiss it via `POST /notifications/dismiss-low-stock`, gate `dismiss-low-stock` → `isManager()`.

## The form

Every recipe renders with a quantity box beside it, so **typing a number is what
selects a dish** — there is no checkbox to tick and forget. Search filters the
list; a row hidden by the search **still submits whatever was typed in it**, the
same rule the stock-take sheet follows. Narrowing the view must never quietly
discard an entry.

The preview sums the formulas of every dish entered — each ingredient's required
amount against what is on hand, **red where the shelf cannot cover it**. A short
line is worth reading: stock never goes below zero, so it means the figure
inventory carries is already behind what the kitchen actually has.

> [!tip] An ingredient used by two dishes is merged into one line
> Three toasties and two pad kra pao both want cooking oil. Shown as two lines
> of 0.006 and 0.04 against 3 on hand, each looks comfortable; what matters is
> whether the shelf covers 0.046. The payload carries `inventory_item_id`
> purely so the preview can merge on it.

The recipe payload is serialised with `JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT` — the flags `@json()` would apply. Recipe names are user input and the payload sits inside a `<script>` block; without them a dish named `</script>…` breaks straight out of it.

## What it touches

| Table | Effect |
|---|---|
| `production_batches` | one row **per dish** — recipe, quantity, value |
| `production_batch_lines` | one row per ingredient **consumed** |
| `inventory_items` | one deduction per ingredient; one addition for the finished dish |
| `recipes` | recost cascade, only where the finished dish is itself an ingredient |
| `notifications` | one row per (low item × head chef) |
| `audits` | several |

## Traps

- **Deleting a batch does not undo it.** The ingredients are not put back and the finished dishes are not taken away. The confirm dialog says so.
- **A recipe with no finished dish still consumes its ingredients.** The form says so on the row and again in the preview, but it is the one case where production and sales can both deduct if you are not paying attention — production takes the raw ingredients, and so will the sale.
- **The notification loop is a cross product.** Two low items and two head chefs is four notification rows. Fine at this scale.
- **`quantity_produced` is nullable** so pre-pipeline batches still load. There are none on live, but the column allows it.

## The mathematics

- Batch value — above
- [[Plate cost]] — what one serving costs, and therefore what a batch costs
- [[Low stock threshold]] · [[Inventory monetary value]]

## See also

[[Path — Logging a sale]] · [[Path — Saving a recipe]] · [[Path — Logging a purchase]] · [[Low stock threshold]] · [[Demo sandbox]] · [[Inventory as shared state]]
