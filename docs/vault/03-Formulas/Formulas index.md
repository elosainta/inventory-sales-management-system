# Formulas index

Every number this system computes, in one table. Each links to a note with the derivation, the implementing code, a worked example and the failure modes.

> [!warning] Read the legend before the table
> Subscript $i$ always means "per line item" — but *which* line item depends on the formula. The same $q_i$ is a recipe ingredient quantity under [[Plate cost]], a purchase line quantity under [[Path — Logging a purchase]], and stock on hand under [[Low stock threshold]]. $s$ is a sale in COGS and "spent" in the petty cash balance. $R$ is one sale's revenue in [[Sale revenue]] and a whole month's revenue in [[Gross margin]]. The symbols are conventional within each note and were never reconciled across them; the legends below say which meaning is in force.

## Money

| Quantity               | Formula                                                                 | Code                            | Note                          |
| ---------------------- | ----------------------------------------------------------------------- | ------------------------------- | ----------------------------- |
| Sale revenue           | $\max(0,\ \operatorname{round}(n p - d,\ 2))$                           | `Sale.php:31-34`                | [[Sale revenue]]              |
| Ingredient cost        | $C = \sum_i q_i c_i$                                                    | `Recipe.php:28-35`              | [[Plate cost]]                |
| Plate cost             | $\operatorname{round}\!\left(C\left(1 + \frac{m}{100}\right), 2\right)$ | `Recipe.php:42-49`              | [[Plate cost]]                |
| Recipe profit          | $\operatorname{round}(p_{\text{sell}} - \text{plate cost},\ 2)$         | `Recipe.php:15-18`              | [[Plate cost]]                |
| Purchase / batch total | $\operatorname{round}\!\left(\sum_i q_i p_i,\ 2\right)$                 | `LogPurchase.php:25-30`         | [[Path — Logging a purchase]] |
| COGS                   | $\sum_{s} \text{plateCost}(r_s)\, n_s$                                  | `DashboardController.php:48-50` | [[Cost of goods sold]]        |
| Gross margin           | $\dfrac{R - \text{COGS}}{R} \times 100$                                 | `DashboardController.php:52`    | [[Gross margin]]              |
| Petty cash balance     | $\sum g - \sum s - \sum r$                                              | `DashboardController.php:36`    | [[Petty cash balance]]        |

| Symbol | Meaning | Column |
|---|---|---|
| $n$, $p$, $d$ | units sold on one sale, selling price per unit, discount in **MYR off the whole sale** (not a percentage) | `sales.qty_sold` / `selling_price` / `discount` |
| $q_i$, $c_i$ | quantity of ingredient $i$ in the recipe, and that ingredient's current unit cost | `recipe_ingredients.quantity`, `inventory_items.unit_cost` |
| $C$ | ingredient-only cost of one plate — never stored, recomputed on demand | — |
| $m$ | miscellaneous overhead as a **percent** of $C$ (gas, condiments, small consumables) | `recipes.misc_percent` |
| $p_{\text{sell}}$ | menu price of the recipe | `recipes.selling_price` |
| $q_i$, $p_i$ | quantity and unit price on purchase **line** $i$ | `purchase_lines.quantity` / `unit_price` |
| $s$, $n_s$, $r_s$ | each sale $s$ in the month, its quantity sold, its recipe | `sales.*` joined to `recipes` |
| $\text{plateCost}(r)$ | that recipe's stored plate cost | `recipes.plate_cost` |
| $R$ | **total** revenue for the month — not one sale's | $\sum$ `sales.total_revenue` |
| $g$, $s$, $r$ | given, spent, returned — summed over **all** float issuances, never month-filtered | `float_issuances.amount_given` / `amount_spent` / `amount_returned` |

## Stock

| Quantity | Formula | Code | Note |
|---|---|---|---|
| Monetary value | $m_i = \operatorname{round}(q_i c_i,\ 2)$ | five call sites | [[Inventory monetary value]] |
| Inventory value | $V = \sum_i m_i$ | `DashboardController.php:35` | [[Inventory monetary value]] |
| Sale deduction | $\max(0,\ h_i - q_i n)$ | `LogSale.php:33-34` | [[Path — Logging a sale]] |
| Low stock | $q_i \le 0.5\, t_i$ | `InventoryItem.php:29-34` | [[Low stock threshold]] |
| Tally variance | $v = q_{\text{counted}} - q_{\text{system}}$ | `InventoryTallyLine.php:40-47` | [[Tally variance]] |
| Stock-take balance | $\max(0,\ \text{current} + \text{in} - \text{out})$ | `StockTakeEntry.php` â `balance()` | [[Stock-take movement]] |

| Symbol | Meaning | Column |
|---|---|---|
| $q_i$, $c_i$, $m_i$ | on-hand quantity, unit cost and stored value of inventory item $i$ | `inventory_items.quantity_on_hand` / `unit_cost` / `monetary_value` |
| $V$ | every item's stored value added up — the dashboard's inventory figure | $\sum$ `monetary_value` |
| $h_i$ | on-hand quantity **before** the deduction; here $q_i$ is the recipe quantity per serving and $n$ the quantity sold | `inventory_items.quantity_on_hand` |
| $t_i$ | reorder threshold — a comfortable target, not the trigger point; the $0.5$ is `InventoryItem::LOW_STOCK_FACTOR` | `inventory_items.reorder_threshold` |
| $q_{\text{counted}}$, $q_{\text{system}}$ | what the person physically counted, and the system figure snapshotted at that moment | `inventory_tally_lines.counted_quantity` / `system_quantity` |
| $\text{open}_n$, $\text{close}_{n-1}$ | opening on sheet $n$, closing on the previous sheet — matched by item name **within one section** | `stock_take_entries.opening` / `closing` |

## Loss

| Quantity | Formula | Code | Note |
|---|---|---|---|
| Cost lost | $\operatorname{round}(c_u q_w,\ 2)$ | `LogWastage.php:16-19` | [[Wastage cost and rate]] |
| Wastage rate | $\dfrac{\text{waste}}{\text{spend}} \times 100$ | `DashboardController.php:43` | [[Wastage cost and rate]] |

| Symbol | Meaning | Column |
|---|---|---|
| $c_u$ | the item's unit cost **at the moment of recording** — snapshotted into `cost_lost`, so history does not move when prices change | `inventory_items.unit_cost` |
| $q_w$ | how much was thrown away | `wastage_entries.quantity_wasted` |
| waste, spend | the month's total cost lost over the month's total purchase spend — buying activity, **not** COGS, so the ratio is only a rough signal | $\sum$ `cost_lost` / $\sum$ `purchases.total_amount` |

## People

| Quantity | Formula | Code | Note |
|---|---|---|---|
| Entry average | $\bar{r} = \frac{1}{5}\sum_{k} r_k$ | `FeedbackEntry.php:57-60` | [[Feedback averages]] |
| Question average | $\bar{R}_k = \frac{1}{N}\sum_{e} r_{e,k}$ | `FeedbackEntry.php:79-80` | [[Feedback averages]] |
| Per-person average | mean of entry means | `FeedbackEntry.php:82-89` | [[Feedback averages]] |

| Symbol | Meaning | Column |
|---|---|---|
| $k$, $r_k$ | one of the five fixed questions in `FeedbackEntry::QUESTIONS`, and its 1–5 rating | `feedback_entries.rating_*` |
| $\bar{r}$ | one entry's mean across all five questions — always $\frac{1}{5}$, the questions are fixed | — |
| $e$, $N$ | each entry in the selected month, and how many there are | `feedback_entries` filtered on `created_at` |
| $\bar{R}_k$ | the month's average for question $k$ across every entry — `null`, not $0$, when the month is empty | — |

## Cross-cutting

- [[Rounding and money]] — the `DECIMAL(12,2)` / `round(…, 2)` discipline and where it is skipped
- [[Inventory as shared state]] — why changing one number moves several others

## Patterns worth recognising

**Guarded division.** Every ratio guards its denominator:

```php
$rate = $denominator > 0 ? round(($num / $den) * 100, 1) : 0;
```

Returning `0` rather than `null` means the view needs no special case. It also means "no data" and "genuinely zero" render identically — a real, accepted ambiguity.

**Clamp at zero.** `max(0, …)` appears in [[Sale revenue]] and both stock-deduction paths. A discount larger than the sale, or a deduction larger than stock, floors rather than going negative. Prevents nonsense propagating downstream, at the cost of hiding the mistake.

**Round at the boundary, not in the loop.** Sums accumulate at full precision and round once on the way into a `DECIMAL(12,2)` column. [[Rounding and money]].

**Snapshot, do not recompute.** `cost_lost`, `system_quantity`, `item_name`, `total_revenue` are all stored at write time. History does not shift when today's prices change.

**One definition, many callers.** [[Sale revenue]] is a static method precisely so `LogSale` and `SaleController@update` cannot disagree. Where that discipline is *not* followed — [[Low stock threshold]] has a PHP and a SQL implementation — the note says so.

## See also

[[Code paths index]] · [[Path — Rendering the dashboard]] · [[Home]]
