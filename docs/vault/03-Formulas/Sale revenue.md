# Sale revenue

What actually lands in the till for one sale.

## Formula

$$R = \max\Big(0,\; \operatorname{round}(n \cdot p - d,\; 2)\Big)$$

| Symbol | Meaning | Column |
|---|---|---|
| $n$ | quantity sold | `sales.qty_sold` |
| $p$ | selling price per unit | `sales.selling_price` |
| $d$ | discount, absolute MYR | `sales.discount` |
| $R$ | revenue | `sales.total_revenue` |

## Code

`app/Models/Sale.php:31-34`

```php
public static function revenue(int|float $qty, int|float $price, int|float $discount = 0): float
{
    return max(0, round($qty * $price - $discount, 2));
}
```

## The three decisions in one line

### `$discount` is absolute, not a percentage

`d` is MYR off the total, not a rate. A 3-plate sale at RM 12 with `discount = 5` is RM 31, not RM 34.20. Absolute is what a kitchen actually negotiates — *"just make it thirty"* — and it needs no second rounding step.

### Discount applies to the whole sale, not per unit

$n p - d$, not $n(p - d)$. The difference on 3 plates with a RM 5 discount is RM 31 versus RM 21.

### `max(0, …)` clamps at zero

A discount larger than the gross would otherwise record **negative revenue**, which would flow into monthly totals, [[Gross margin]] and the PDF. Clamping keeps the corruption from spreading. The cost is that the mistake becomes invisible — a RM 500 discount on a RM 30 sale silently records RM 0.

## Worked examples

| $n$ | $p$ | $d$ | Computation | $R$ |
|---|---|---|---|---|
| 3 | 12.00 | 0 | $36 - 0$ | **36.00** |
| 3 | 12.00 | 5.00 | $36 - 5$ | **31.00** |
| 1 | 8.50 | 8.50 | $8.50 - 8.50$ | **0.00** |
| 1 | 8.50 | 20.00 | $-11.50 \to$ clamped | **0.00** |
| 7 | 4.35 | 1.05 | $30.45 - 1.05$ | **29.40** |

## Why it is a static method

Two paths compute revenue:

- `LogSale.php:15-19` — logging a new sale
- `SaleController@update` — editing one

Inlining the arithmetic in both is how a system ends up with an edit form that quietly disagrees with the log form about discounts. One function, two callers, no possible drift.

> [!note] The `(float)` cast at the call site is required
> ```php
> (float) ($data['discount'] ?? 0)
> ```
> A blank discount field arrives as `''` unless `ConvertEmptyStringsToNull` is in the middleware stack. `''` is not `null`, so `?? 0` does not catch it, and `''` into an `int|float` parameter is a `TypeError` → 500.

## Test

`tests/Unit/SaleRevenueTest.php` is the runnable check on this function. It is the only unit test covering a money formula.

## Where it flows

```
Sale::revenue()  →  sales.total_revenue
                        ├─→ salesThisMonth   (dashboard KPI)
                        ├─→ salesTrend       (daily chart)
                        ├─→ Gross margin     (numerator R)
                        └─→ sales PDF        (with discount + open-order totals)
```

## Open orders

`is_open_order` does **not** change this formula. An open order is an ordinary sale carrying a discount; the flag only affects display — an "Open order" pill in the list and separate totals in the PDF. A normal sale can carry a discount too. See [[Path — Logging a sale]].

## See also

[[Path — Logging a sale]] · [[Gross margin]] · [[Rounding and money]] · [[Formulas index]]
