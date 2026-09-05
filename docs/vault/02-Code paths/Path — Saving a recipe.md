# Path — Saving a recipe

The only write path that does not touch stock. It reads `unit_cost` and produces `plate_cost`, the number every margin calculation downstream depends on.

## Trigger

| | |
|---|---|
| Route | `POST /recipes`, `PUT /recipes/{recipe}` → `RecipeController` |
| Gate | `manage-recipes` → `isManager()` |
| Action | `app/Domain/Recipes/Actions/SaveRecipe.php` |

Handles both create and update — the optional second argument decides which.

```php
public function execute(array $data, ?Recipe $recipe = null): Recipe
```

## The line

### 1 · `SaveRecipe.php:31-33` — default the overhead

```php
if (! isset($data['misc_percent']) || $data['misc_percent'] === '' || $data['misc_percent'] === null) {
    $data['misc_percent'] = 30;
}
```

Three separate checks because a blank HTML form field arrives as `''`, which is neither unset nor null. The same `''` hazard appears in [[Path — Logging a sale]].

30% is the house default for gas, condiments and small consumables. Explained in [[Plate cost]].

### 2 · `SaveRecipe.php:35` — write a placeholder

```php
$data['plate_cost'] = 0;
```

`plate_cost` cannot be computed until the ingredient rows exist, and the ingredient rows need a `recipe_id`. Zero is a placeholder overwritten in step 5 — inside the same transaction, so it is never observable.

### 3 · `SaveRecipe.php:41-46` — merge duplicate ingredients

```php
$mergedQuantities = [];
foreach ($ingredients as $ing) {
    $itemId = $ing['inventory_item_id'];
    $mergedQuantities[$itemId] = ($mergedQuantities[$itemId] ?? 0) + (float) $ing['quantity'];
}
```

$$q_{\text{merged}}(i) = \sum_{r \,:\, \text{item}(r) = i} q_r$$

> [!danger] This exists because of a 500
> `recipe_ingredients` has a unique constraint on `(recipe_id, inventory_item_id)`. Picking the same item twice in the form — easy to do with a long dropdown — would violate it. Summing is also the semantically right answer: "200g flour here, 100g flour there" genuinely is 300g of flour.

### 4 · `SaveRecipe.php:48-53` — delete and re-insert

```php
if ($recipe) {
    $recipe->update($data);
    $recipe->ingredients()->delete();
} else {
    $recipe = Recipe::create($data);
}
```

Update is destructive-then-rebuild rather than a diff. Simpler, and correct inside a transaction. The cost is audit noise: every edit deletes and recreates every ingredient row. Acceptable, because `RecipeIngredient` is **not** audited — only `Recipe` itself is.

### 5 · `SaveRecipe.php:64` — compute the real cost

```php
$recipe->recalculatePlateCost();
```

`app/Models/Recipe.php:42-49`:

```php
$ingredients = $this->ingredientCost();
$misc        = $ingredients * ((float) ($this->misc_percent ?? 0) / 100);

$this->plate_cost = round($ingredients + $misc, 2);
$this->save();
```

$$\text{plate cost} = \operatorname{round}\!\left(C \cdot \left(1 + \frac{m}{100}\right),\; 2\right), \qquad C = \sum_i q_i \cdot c_i$$

Full derivation, worked example and the reason overhead is proportional rather than flat: [[Plate cost]].

## Who else calls `recalculatePlateCost()`

Three callers, and knowing all three matters:

| Caller | Trigger |
|---|---|
| `SaveRecipe.php:64` | recipe edited |
| `LogPurchase.php:60-61` | an ingredient was re-priced by a purchase |
| `LogProduction.php:63-64` | an ingredient was re-priced by production |

A recipe's cost therefore changes without anyone editing the recipe. That is the intended behaviour — [[Inventory as shared state]].

## The mathematics

- [[Plate cost]] — ingredient cost + proportional overhead
- Profit, `Recipe.php:15-18`: $\text{profit} = \operatorname{round}(p_{\text{sell}} - \text{plate cost},\, 2)$ — an accessor, computed on read, never stored, so it cannot go stale
- Feeds [[Cost of goods sold]] and [[Gross margin]]

## What it touches

| Table | Effect |
|---|---|
| `recipes` | one row, twice (create/update, then the `plate_cost` save) |
| `recipe_ingredients` | full delete + re-insert on update |
| `audits` | recipe rows only — ingredients are not audited |

## Traps

- **Soft deletes.** `Recipe` uses `SoftDeletes`. A deleted recipe still has sales pointing at it, and those sales still contribute to [[Cost of goods sold]] because the join finds the row.
- **`misc_percent` is per recipe**, not global. Changing the house rate means editing every recipe.
- **Ingredient cost silently treats a missing item as free** — `$ing->inventoryItem?->unit_cost ?? 0` at `Recipe.php:33`.

## See also

[[Plate cost]] · [[Path — Logging a purchase]] · [[Inventory as shared state]] · [[Cost of goods sold]]
