# Gross margin

The headline number. What fraction of revenue survives the cost of making the food.

## Formula

$$GM\% = \begin{cases}
\operatorname{round}\!\left(\dfrac{R - \text{COGS}}{R} \times 100,\; 1\right) & R > 0 \\[2.5ex]
0 & R = 0
\end{cases}$$

| Symbol | Meaning | Source |
|---|---|---|
| $R$ | revenue this month | $\sum$ `sales.total_revenue` — [[Sale revenue]] |
| COGS | cost of what was sold | [[Cost of goods sold]] |

## Code

`app/Http/Controllers/DashboardController.php:52`

```php
$grossMargin = $salesThisMonth > 0
    ? round((($salesThisMonth - $cogs) / $salesThisMonth) * 100, 1)
    : 0;
```

## Margin, not markup

The two get confused constantly and differ by a lot.

$$\text{margin} = \frac{R - C}{R} \qquad\qquad \text{markup} = \frac{R - C}{C}$$

Cost RM 5, sell RM 12:

- margin $= \frac{7}{12} = 58.3\%$ — of every ringgit taken, 58.3 sen is gross profit
- markup $= \frac{7}{5} = 140\%$ — the price is 2.4× the cost

**Margin divides by revenue.** That is what this system reports, and it is the right choice: it is directly comparable to revenue-share figures like [[Wastage cost and rate]], and it cannot exceed 100%.

## Reading the number

$R$ is net of discounts — [[Sale revenue]] subtracts them before the total is stored. So discounting compresses margin automatically, with no separate handling. A month heavy on discounted staff [[Path — Logging a sale]] open orders will show a visibly lower margin, which is correct: those meals cost full price to make and brought in less.

## Worked example

| | |
|---|---|
| Revenue $R$ | 6,240.00 |
| COGS | 2,515.40 |
| Gross profit | 3,724.60 |
| $GM\%$ | $\operatorname{round}(59.6891\ldots,\ 1) = \mathbf{59.7}$ |

## What it is not

**Not net margin.** Nothing below the gross line is subtracted:

| Excluded | Where it lives instead |
|---|---|
| Wastage | [[Wastage cost and rate]] — reported separately |
| Wages | not tracked at all |
| Rent, utilities | not tracked at all |
| Petty cash spend | [[Petty cash balance]] |

Wastage in particular is a **real cost that this figure ignores**. A month at 60% gross margin with a 15% wastage rate is a very different business from 60% with 2%. The dashboard shows both side by side precisely so they are read together.

**Not stable over time.** COGS uses current `plate_cost`, so re-running an old month after a price rise gives a lower margin. Explained in [[Cost of goods sold]].

## The zero guard

`$salesThisMonth > 0` prevents division by zero in a month with no sales.

Returning `0` rather than `null` means the view needs no special case — but "no sales" and "sold at exactly cost" both render as **0.0%**. In practice a month with no sales also shows RM 0.00 revenue right next to it, so the ambiguity is visible on screen.

## Sanity ranges

| $GM\%$ | Reading |
|---|---|
| < 0 | selling below cost — check `plate_cost` and discounts |
| 0 | no sales, or exactly break-even |
| 30–50 | low for food service; check pricing |
| 55–70 | normal for a kitchen at this scale |
| > 80 | suspicious — likely recipes sitting at `plate_cost = 0` |

The `> 80` case is worth checking rather than celebrating. `Recipe::where('plate_cost', 0)->get()`.

## See also

[[Cost of goods sold]] · [[Sale revenue]] · [[Wastage cost and rate]] · [[Path — Rendering the dashboard]]
