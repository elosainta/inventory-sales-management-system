# Petty cash balance

Float handed to chefs for market runs. One subtraction, and a genuinely misleading one.

## Formula

$$B = \sum g - \sum s - \sum r$$

| Symbol | Meaning | Column |
|---|---|---|
| $g$ | amount given out | `float_issuances.amount_given` |
| $s$ | amount spent | `float_issuances.amount_spent` |
| $r$ | amount returned | `float_issuances.amount_returned` |

## Code

`app/Http/Controllers/DashboardController.php:36`

```php
$pettyCashBalance = FloatIssuance::sum('amount_given')
                  - FloatIssuance::sum('amount_spent')
                  - FloatIssuance::sum('amount_returned');
```

Three separate aggregate queries, subtracted in PHP. Could be one `selectRaw`; at this scale it does not matter and the current form reads more clearly.

## What it actually measures

Not "cash in the tin." Read the lifecycle of one issuance:

1. Owner gives Morgan RM 200 for the market → $g = 200$
2. Morgan spends RM 173.40 → $s = 173.40$
3. Morgan returns RM 26.60 → $r = 26.60$

$$B = 200 - 173.40 - 26.60 = \mathbf{0}$$

A **fully reconciled** issuance contributes zero. So:

$$B = \text{float that is out and not yet accounted for}$$

It is an *outstanding* figure — money handed over that has neither been spent-and-recorded nor handed back.

| $B$ | Meaning |
|---|---|
| 0 | everything reconciled |
| positive | float is out with someone, or spending is not yet recorded |
| negative | more recorded as spent + returned than was ever given — a data error |

> [!danger] A negative balance is always a mistake, and nothing flags it
> There is no `max(0, …)` clamp here and no validation that $s + r \le g$ on an issuance. A double-entered receipt, or a return recorded twice, produces a negative balance that sits silently on the dashboard. Unlike [[Sale revenue]], the nonsense is at least *visible* — arguably better than being clamped away.

## Worked example

| Chef | $g$ | $s$ | $r$ | Contribution |
|---|---|---|---|---|
| Morgan (closed) | 200.00 | 173.40 | 26.60 | 0.00 |
| Chris (closed) | 150.00 | 150.00 | 0.00 | 0.00 |
| Dani (spent, not returned) | 300.00 | 245.00 | 0.00 | 55.00 |
| Sam (just issued) | 500.00 | 0.00 | 0.00 | 500.00 |

$$B = 1150.00 - 568.40 - 26.60 = \mathbf{555.00}$$

RM 55 of unreturned change with Dani, RM 500 not yet spent with Sam.

## Always current

Never month-filtered, unlike sales or wastage. Outstanding float is a **stock** quantity — a level right now — not a flow over a period. Same reasoning as [[Inventory monetary value]]. [[Path — Rendering the dashboard]] has the full split.

## Access

Route `/float`, gate `manage-float` → `isManager()` (Owner + Head Chef). Visible in both sidebars. Issuing writes one row and derives nothing, so `FloatIssuanceController::store` calls `FloatIssuance::create()` directly — the `IssueFloat` action that used to wrap that one line was deleted in 1.10.48. See [[Layering rules]].

`FloatIssuance` carries `LogsActivity`, so every issuance, spend and return is in the [[Audit trail]].

## Not in gross margin

Petty cash spend appears nowhere in [[Gross margin]] or [[Cost of goods sold]]. Market buying that flows through float and gets recorded as a `market_purchase` reaches inventory and therefore eventually reaches COGS; float spending that is never turned into a purchase record reaches nothing. That gap is a reconciliation question, not a formula question.

## See also

[[Path — Rendering the dashboard]] · [[Audit trail]] · [[Roles]] · [[Formulas index]]
