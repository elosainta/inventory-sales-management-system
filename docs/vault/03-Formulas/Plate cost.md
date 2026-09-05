# Plate cost

What one serving of a dish costs to make. The foundation of [[Cost of goods sold]] and therefore of [[Gross margin]] — get this wrong and every financial figure on the dashboard is wrong.

## Formula

Two steps.

**Ingredient cost** — sum over the recipe:

$$C = \sum_{i \in \text{ingredients}} q_i \cdot c_i$$

**Plate cost** — add proportional overhead:

$$P = \operatorname{round}\Big(C + C \cdot \tfrac{m}{100},\; 2\Big) = \operatorname{round}\Big(C \cdot \big(1 + \tfrac{m}{100}\big),\; 2\Big)$$

| Symbol | Meaning | Column |
|---|---|---|
| $q_i$ | quantity of ingredient $i$ per recipe | `recipe_ingredients.quantity` |
| $c_i$ | current unit cost of ingredient $i$ | `inventory_items.unit_cost` |
| $C$ | ingredient-only cost | not stored — computed on demand |
| $m$ | miscellaneous overhead percent, default 30 | `recipes.misc_percent` |
| $P$ | plate cost | `recipes.plate_cost` |

## Code

`app/Models/Recipe.php:28-35`

```php
public function ingredientCost(): float
{
    return (float) $this->ingredients()
        ->with('inventoryItem')
        ->get()
        ->sum(fn($ing) => $ing->quantity * ($ing->inventoryItem?->unit_cost ?? 0));
}
```

`app/Models/Recipe.php:42-49`

```php
public function recalculatePlateCost(): void
{
    $ingredients = $this->ingredientCost();
    $misc        = $ingredients * ((float) ($this->misc_percent ?? 0) / 100);

    $this->plate_cost = round($ingredients + $misc, 2);
    $this->save();
}
```

## Why overhead is proportional, not flat

$m$ multiplies $C$; it is not a fixed RM added on. An expensive dish is assumed to consume proportionally more gas, condiments and consumables than a cheap one.

That is an approximation. A slow-cooked cheap stew burns far more gas than a plated expensive salad, so the model under-costs the stew and over-costs the salad. The correction is per recipe: `misc_percent` is a **per-recipe column**, so a gas-heavy dish can be set to 60 while a cold dish is set to 5. The 30% default is a starting point, not a claim.

> [!note] 30% is the house default, applied only when the field is blank
> `SaveRecipe.php:31-33` checks three ways — unset, `''`, and `null` — because a blank HTML field arrives as `''`, which is neither of the other two.

## Worked example — Nasi Lemak

| Ingredient | $q_i$ | $c_i$ | $q_i c_i$ |
|---|---|---|---|
| Rice | 0.25 kg | 4.80 | 1.20 |
| Coconut milk | 0.10 L | 6.50 | 0.65 |
| Anchovies | 0.03 kg | 32.00 | 0.96 |
| Peanuts | 0.02 kg | 18.00 | 0.36 |
| Egg | 1 | 0.55 | 0.55 |
| Sambal | 0.05 kg | 9.00 | 0.45 |

$$C = 1.20 + 0.65 + 0.96 + 0.36 + 0.55 + 0.45 = 4.17$$
$$\text{misc} = 4.17 \times 0.30 = 1.251$$
$$P = \operatorname{round}(4.17 + 1.251,\ 2) = \operatorname{round}(5.421,\ 2) = \mathbf{5.42}$$

At a selling price of RM 12.00:

$$\text{profit} = \operatorname{round}(12.00 - 5.42,\ 2) = \mathbf{6.58}$$
$$\text{margin} = \frac{6.58}{12.00} \times 100 = \mathbf{54.8\%}$$

## Profit is an accessor, not a column

`app/Models/Recipe.php:15-18`

```php
public function getProfitAttribute(): float
{
    return round($this->selling_price - $this->plate_cost, 2);
}
```

Computed on read. It cannot go stale, and it needs no migration when the definition changes. `plate_cost` **is** stored, because it is expensive to compute (a join and a sum per recipe) and is read on every dashboard load.

That asymmetry — cheap things computed, expensive things cached — is the rule this codebase follows.

## When it recalculates

`plate_cost` is a cache, and three events invalidate it:

| Trigger | Call site |
|---|---|
| Recipe created or edited | `SaveRecipe.php:64` |
| An ingredient re-priced by a purchase | `LogPurchase.php:60-61` |
| An ingredient re-priced by production | `LogProduction.php:63-64` |

$$\text{recost} = \{\, r : \exists\, i \in \text{ingredients}(r),\; i \in \text{updated} \,\}$$

**A recipe's cost changes without anyone editing the recipe.** That is the intended behaviour and it is what keeps margins honest as market prices move. It also means the number on screen depends on when you last bought something. [[Inventory as shared state]].

## Failure modes

- **A missing inventory item counts as free.** `?->unit_cost ?? 0` silently treats a deleted ingredient as costing nothing, understating plate cost with no warning.
- **`unit_cost` is last-price-wins, not averaged.** One panic buy at a high price permanently reprices the ingredient and every recipe using it. There is no weighted average and no per-lot costing.
- **`serving_size` does not divide.** The column exists but the formula never uses it. If a recipe's quantities are for 4 servings, $P$ is the cost of all 4, not of one plate — and [[Cost of goods sold]] then multiplies that by plates sold. Enter per-serving quantities.
- **No stale detection.** If a recost is ever missed, nothing notices. The fix is `php artisan tinker` → `Recipe::each->recalculatePlateCost()`.

## See also

[[Path — Saving a recipe]] · [[Cost of goods sold]] · [[Inventory as shared state]] · [[Rounding and money]]
