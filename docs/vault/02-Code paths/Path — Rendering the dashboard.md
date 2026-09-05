# Path — Rendering the dashboard

The Owner's answer page. One controller method, `DashboardController@index`, ~100 lines of aggregate queries, no writes. This is where every formula in the vault ends up on screen.

## Trigger

| | |
|---|---|
| Route | `GET /dashboard` → `DashboardController@index` |
| Gate | `view-dashboard` → `isManager()` |
| View | `resources/views/dashboard.blade.php` |

## Three redirects before the gate

```php
if (auth()->user()->isJuniorChef()) return redirect()->route('prep.index');
if (auth()->user()->isAdmin())      return redirect()->route('support-tickets.index');

Gate::authorize('view-dashboard');
```

> [!note] The only place `Gate::authorize()` is not literally the first line
> Junior chefs and admins have no dashboard, so redirecting them somewhere useful beats a 403. The gate still runs before any data is read, so nothing leaks. Everyone else who fails it gets the 403.

## Two clocks

```php
$month = request('month', now()->format('Y-m'));
[$year, $mon] = explode('-', $month);
```

Figures split into two families, and confusing them is the most common misreading of this page:

| Always current | Month-filtered |
|---|---|
| `inventoryValue` | `salesThisMonth` |
| `pettyCashBalance` | `purchaseSpend` |
| `lowStockCount` + list | `wasteCost`, `wastageRate` |
| `inventoryByCategory` | `cogs`, `grossMargin` |
| Owner's full data lists | all four trend charts, top wasted, spend by supplier |

Stock is a **stock** quantity — a level at a point in time; "inventory value in June" is meaningless. Sales are a **flow** — a rate over a period. The split is that distinction, not an oversight.

## The KPIs, line by line

### `:35` Inventory value

```php
$inventoryValue = InventoryItem::sum('monetary_value');
```

$$V = \sum_i m_i$$

Summing the stored column, not recomputing $q_i \cdot c_i$. Every write path is responsible for keeping `monetary_value` correct — see [[Inventory monetary value]].

### `:36` Petty cash balance

```php
FloatIssuance::sum('amount_given') - FloatIssuance::sum('amount_spent') - FloatIssuance::sum('amount_returned');
```

$$B = \sum g - \sum s - \sum r$$

Three separate aggregate queries. [[Petty cash balance]] explains what the figure actually means — and why it is easy to misread.

### `:37` Low stock count

```php
InventoryItem::whereRaw('quantity_on_hand <= reorder_threshold * ?', [InventoryItem::LOW_STOCK_FACTOR])->count();
```

The SQL mirror of `InventoryItem::isLowStock()`. Two implementations of one predicate, bound to the same constant so they cannot drift on the factor — though the comparison itself is duplicated. [[Low stock threshold]].

### `:41-43` Sales, spend, waste, rate

```php
$wastageRate = $purchaseSpend > 0 ? round(($wasteCost / $purchaseSpend) * 100, 1) : 0;
```

$$W\% = \begin{cases} \operatorname{round}\!\left(\dfrac{\text{waste}}{\text{spend}} \times 100,\; 1\right) & \text{spend} > 0 \\[2ex] 0 & \text{otherwise}\end{cases}$$

The guard is division-by-zero protection. [[Wastage cost and rate]] covers why the denominator is spend and what that distorts.

### `:48-50` COGS — the one non-obvious query

```php
$cogs = Sale::whereYear('sale_date', $year)->whereMonth('sale_date', $mon)
    ->join('recipes', 'sales.recipe_id', '=', 'recipes.id')
    ->sum(DB::raw('recipes.plate_cost * sales.qty_sold'));
```

$$\text{COGS} = \sum_{s \in \text{month}} \text{plateCost}(r_s) \times n_s$$

The product is computed **in SQL**, per row, then summed — not in PHP. Added in commit `cbdecae`; before that, gross margin used purchase spend as the cost base, which measured buying activity rather than the cost of what was sold. [[Cost of goods sold]].

### `:52` Gross margin

```php
$grossMargin = $salesThisMonth > 0 ? round((($salesThisMonth - $cogs) / $salesThisMonth) * 100, 1) : 0;
```

$$GM\% = \frac{R - \text{COGS}}{R} \times 100$$

[[Gross margin]].

## The charts

Four `GROUP BY` aggregates feeding Chart.js:

| Variable | Group by | Chart |
|---|---|---|
| `salesTrend` | `DATE(sale_date)` | daily revenue line |
| `purchaseTrend` | `DATE(purchase_date)` | daily spend line |
| `wastageByReason` | `reason` | breakdown — the *why* |
| `wastageTrend` | `DATE(recorded_date)` | daily waste line |
| `inventoryByCategory` | `category` | current stock value, unfiltered |

Grouping on `DATE(col)` means days with no activity are simply absent, not zero. The chart layer has to handle the gaps.

## Bottom lists

`lowStockItems` (5, current), `topWasted` (5, month, eager-loaded), `spendBySupplier` (5, month, eager-loaded).

## The Owner block

`:88-100` — a role check that is **not** a gate:

```php
if (auth()->user()?->role === 'owner') {
    $ownerData = [ /* full inventory, last 7 of each */ ];
}
```

> [!danger] Hand-rolled role check
> `CLAUDE.md` says "Never hand-roll role checks." This is one, and it uses a string literal rather than `User::ROLE_OWNER`. It only *widens* what an owner sees, so it is not a security hole — but it is the kind of drift the gate rule exists to prevent. `$user->isOwner()` is the drop-in fix.

`inventory` is the whole list; `4a0a5aa`/`40a5044` changed it from 7 items after the Owner asked to see everything.

## Demo

```php
$hideSales = false;
```

Kept as a variable the Blade still reads. Demo accounts now run against a physically separate database ([[Demo sandbox]]), so every figure is already demo-only and safe to show in full. The flag is a stub awaiting deletion from the view.

## Query cost

Roughly 25 queries per load, none indexed-tuned, no caching. At five users and a few thousand rows that is invisible. The first thing to hurt at scale would be the unbounded `inventoryValue` and `inventoryByCategory` sums.

## See also

[[Formulas index]] · [[Cost of goods sold]] · [[Gross margin]] · [[Inventory as shared state]]
