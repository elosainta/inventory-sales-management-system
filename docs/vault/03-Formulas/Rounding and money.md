# Rounding and money

Every money value in this system is MYR, stored `DECIMAL(12,2)`, rounded with PHP's `round()`, and displayed through exactly one function.

## The three rules

### 1 · Store as `DECIMAL(12,2)`

Never `FLOAT`, never `DOUBLE`.

Binary floating point cannot represent 0.1 exactly. Sum enough of them and `SUM()` returns `1234.5600000000002`. `DECIMAL` is base-10 fixed point — exact for currency, and the arithmetic MariaDB does on it is exact too.

12 digits total, 2 after the point: up to RM 9,999,999,999.99. Adequate for one kitchen in Malaysia.

### 2 · Round at the boundary, not in the loop

`LogPurchase.php:25-30`:

```php
$total = 0;
foreach ($lines as $line) {
    $total += $line['quantity'] * $line['unit_price'];   // full precision
}
$data['total_amount'] = round($total, 2);                 // round once
```

$$\operatorname{round}\!\left(\sum_i q_i p_i,\ 2\right) \;\neq\; \sum_i \operatorname{round}(q_i p_i,\ 2)$$

Rounding each line first lets up to half a sen of error accumulate per line. On a 30-line invoice that is a visible mismatch against the paper the supplier handed over — the single most common way an ordering system loses the Owner's trust.

Line totals *are* rounded individually at `LogPurchase.php:33`, but that value is stored for display only. It is never an input to the sum.

### 3 · Display through `@money()` only

`app/Support/Money.php`:

```php
public static function format(int|float|string|null $amount): string
{
    $value = (float) ($amount ?? 0);

    if ($value < 0) {
        return '-RM ' . number_format(abs($value), 2);
    }

    return 'RM ' . number_format($value, 2);
}
```

Registered as a Blade directive in `AppServiceProvider::boot()`:

```php
Blade::directive('money', fn (string $expression) =>
    "<?php echo \App\Support\Money::format($expression); ?>");
```

| Input | Output |
|---|---|
| `1234.5` | `RM 1,234.50` |
| `0` | `RM 0.00` |
| `-50.25` | `-RM 50.25` |
| `null` | `RM 0.00` |

Three things it guarantees: always 2 decimals, always thousands separators, and the minus sign **outside** the currency symbol (`-RM 50.25`, not `RM -50.25`).

Never write `RM {{ number_format($x, 2) }}` in a Blade file. There is one place to change currency formatting and this is it.

## How PHP's `round()` behaves

`round()` is **half away from zero**:

$$\operatorname{round}(2.5) = 3, \qquad \operatorname{round}(-2.5) = -3, \qquad \operatorname{round}(2.4) = 2$$

Not banker's rounding (half to even), which would give 2. Every money calculation in this system uses the default mode.

The half-away-from-zero bias means repeated rounding drifts upward very slightly. With rounding happening once per stored value rather than per intermediate step (rule 2), it never accumulates enough to matter.

## Where money is rounded

| Value | Site |
|---|---|
| `sales.total_revenue` | `Sale.php:33` |
| `recipes.plate_cost` | `Recipe.php:47` |
| `recipes.profit` (accessor) | `Recipe.php:17` |
| `purchases.total_amount`, `purchase_lines.line_total` | `LogPurchase.php:29, 33` |
| `production_batches.total_value`, lines | `LogProduction.php:26, 33` |
| `wastage_entries.cost_lost` | `LogWastage.php:15-18` |
| `inventory_items.monetary_value` | five sites — [[Inventory monetary value]] |

## Where money is *not* rounded

$$\text{petty cash balance} = \sum g - \sum s - \sum r$$

`DashboardController.php:36` — three `DECIMAL` sums subtracted with no `round()`.

Safe, because `SUM()` over `DECIMAL(12,2)` is exact in MariaDB and subtracting exact 2-decimal values yields an exact 2-decimal value. The rounding would be a no-op. Worth knowing so nobody "fixes" it and assumes the same holds for a `FLOAT` column.

## Percentages round to 1, not 2

$$W\%,\ GM\% \to \operatorname{round}(x,\ 1)$$

`59.7%`, not `59.69%`. These are indicators for a decision, not accounting figures; the second decimal is noise given the size of the underlying approximations. See [[Gross margin]] and [[Wastage cost and rate]].

## Quantities are not money

`quantity_on_hand`, `counted_quantity` and recipe quantities are also `DECIMAL`, but they are counts and weights, not currency. `@money()` must never be used on them.

`InventoryTallyLine` casts both quantity columns to `decimal:2` (`InventoryTallyLine.php:21-24`), which returns them as **strings** from Eloquent — hence the explicit `(float)` casts inside `variance()`. See [[Tally variance]].

## See also

[[Inventory monetary value]] · [[Sale revenue]] · [[Plate cost]] · [[Formulas index]]
