# Cost of goods sold

What the food you actually sold cost to make. **Not** what you spent buying stock.

The distinction is the entire point of this figure, and getting it wrong was a real bug — see the history below.

## Formula

$$\text{COGS} = \sum_{s \,\in\, \text{month}} \text{plateCost}(r_s) \times n_s$$

| Symbol | Meaning |
|---|---|
| $s$ | a sale in the selected month |
| $r_s$ | the recipe that sale is for |
| $n_s$ | quantity sold on that sale |
| $\text{plateCost}(r)$ | `recipes.plate_cost` — see [[Plate cost]] |

## Code

`app/Http/Controllers/DashboardController.php:48-50`

```php
$cogs = Sale::whereYear('sale_date', $year)->whereMonth('sale_date', $mon)
    ->join('recipes', 'sales.recipe_id', '=', 'recipes.id')
    ->sum(DB::raw('recipes.plate_cost * sales.qty_sold'));
```

The multiplication happens **in SQL, per row**, then MariaDB sums. Not `->get()` then a PHP loop. One row leaves the database.

## Why not purchase spend

This is the whole reason the figure exists.

| | Measures | Moves when |
|---|---|---|
| **Purchase spend** | buying activity — stock coming *in* | you stock up for a wedding next week |
| **COGS** | cost of what was *sold* | you sell a plate |

Buy RM 5,000 of stock on the 30th and sell nothing. Purchase spend says RM 5,000. COGS says RM 0. COGS is right — none of it has been sold, it is sitting on the shelf as [[Inventory monetary value]].

> [!danger] This was a real bug, fixed in commit `cbdecae` (2026-07-20)
> [[Gross margin]] previously used purchase spend as its cost base. On any month with a large stock-up the margin looked catastrophic; on a month living off existing stock it looked implausibly good. The reported margin tracked *purchasing rhythm*, not profitability.

## Worked example

March sales:

| Dish | plate cost | sold | contribution |
|---|---|---|---|
| Nasi Lemak | 5.42 | 120 | 650.40 |
| Laksa the region | 7.80 | 85 | 663.00 |
| Kolo Mee | 4.15 | 200 | 830.00 |
| Teh Tarik | 1.20 | 310 | 372.00 |

$$\text{COGS} = 650.40 + 663.00 + 830.00 + 372.00 = \mathbf{2{,}515.40}$$

If revenue that month was RM 6,240.00:

$$GM\% = \frac{6240.00 - 2515.40}{6240.00} \times 100 = \frac{3724.60}{6240.00} \times 100 = \mathbf{59.7\%}$$

Purchase spend in March might have been RM 4,100 — which would have given a misleading 34.3%.

## The soft assumption

`plate_cost` is the **current** value, not the value at the time of the sale. `recipes.plate_cost` is a single mutable column with no history, and it is rewritten whenever an ingredient is re-priced ([[Path — Logging a purchase]]).

So COGS for March, computed in August, uses August's costs. If flour doubled in June, March's reported COGS silently rises.

**Consequence:** historical COGS is not stable. Re-running last quarter's report after a price rise gives a different number.

**Why it is accepted:** the alternative is snapshotting `plate_cost` onto every sale row — a column, a migration, and a permanent obligation for every write path to keep it current. For a single kitchen answering *"is my margin healthy right now?"*, the current-cost view is arguably the more useful one anyway. If historical accuracy ever matters, snapshot `plate_cost_at_sale` on `sales` and change the `DB::raw` to read it.

This is the same snapshot-versus-live trade made **the other way** in [[Wastage cost and rate]], where `cost_lost` *is* frozen at write time.

## Edge cases

| Case | Behaviour |
|---|---|
| Recipe soft-deleted | still joins — the row exists, so the sale still contributes |
| `recipe_id` null | impossible; the column is required |
| `plate_cost` is 0 | contributes nothing; COGS understated and margin overstated |
| No sales in month | `SUM` returns null, coerced to 0 → margin guard returns 0 |

> [!note] The `plate_cost = 0` case is the quiet one
> A recipe saved before its recost, or one whose ingredients all resolve to a missing inventory item, sits at zero and makes the kitchen look more profitable than it is. `Recipe::where('plate_cost', 0)->get()` is the check worth running occasionally.

## See also

[[Gross margin]] · [[Plate cost]] · [[Path — Rendering the dashboard]] · [[Wastage cost and rate]]
