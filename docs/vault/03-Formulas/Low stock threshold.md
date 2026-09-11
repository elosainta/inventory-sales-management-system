# Low stock threshold

When an item counts as running out. A one-line predicate with a deliberately counter-intuitive constant.

## Formula

$$\text{low}(i) \iff q_i \le f \cdot t_i, \qquad f = 0.5$$

| Symbol | Meaning | Column |
|---|---|---|
| $q_i$ | quantity on hand | `inventory_items.quantity_on_hand` |
| $t_i$ | reorder threshold | `inventory_items.reorder_threshold` |
| $f$ | low-stock factor | `InventoryItem::LOW_STOCK_FACTOR` |

## Code

`app/Models/InventoryItem.php:23-34`

```php
/**
 * Stock is considered "low" once it falls to half the reorder threshold
 * or less. The threshold is treated as a comfortable target rather than
 * the trigger point, so an item only flags Low when seriously depleted.
 */
public const LOW_STOCK_FACTOR = 0.5;

public function isLowStock(): bool
{
    return $this->quantity_on_hand <= $this->reorder_threshold * self::LOW_STOCK_FACTOR;
}
```

## Why half, and not the threshold itself

The obvious reading of "reorder threshold" is *the level at which you reorder*. Here it is not — it is the **comfortable target level**, and the alert fires at half of it.

The reason is alert fatigue. A kitchen with hundreds of items where every item alerts at its target level produces a permanent wall of red that everyone learns to ignore. Halving means an item only shouts when it is genuinely depleted.

**The consequence, stated plainly:** by the time the alert fires, you are already at half your intended buffer. This is a *reaction* threshold, not a *planning* one. The Owner's planning view is the dashboard's low-stock list — same predicate, but visible on every page load rather than pushed as a notification.

Tuning is one constant:

| $f$ | Behaviour |
|---|---|
| 1.0 | alert at target — noisy, but earliest warning |
| 0.5 | current — alerts when seriously depleted |
| 0.25 | nearly silent; effectively an emergency signal |

Changing `LOW_STOCK_FACTOR` changes both implementations below at once.

## Two implementations

The predicate exists twice.

**PHP**, `InventoryItem.php:33` — used by [[Path — Logging production]] to decide whether to notify.

**SQL**, `DashboardController.php:37` and `:80`:

```php
InventoryItem::whereRaw('quantity_on_hand <= reorder_threshold * ?', [InventoryItem::LOW_STOCK_FACTOR])
```

> [!note] Half-shared, and the shared half is the important one
> The **constant** is bound in both places, so the factor cannot drift. The **comparison** is written twice. Change `<=` to `<`, or add a "and the item is active" condition, in one place and the two disagree — the notification and the dashboard count would then tell different stories.
>
> The SQL version exists so counting does not require loading 185 models into PHP. That is the right call; the duplication is the price.

## Worked example

| Item | $q_i$ | $t_i$ | $0.5 t_i$ | Low? |
|---|---|---|---|---|
| Rice | 45 | 50 | 25 | no |
| Chicken thigh | 12 | 30 | 15 | **yes** |
| Prawns | 5 | 10 | 5 | **yes** — `<=` is inclusive |
| Coriander | 0 | 5 | 2.5 | **yes** |
| Salt | 8 | 0 | 0 | no — $8 \le 0$ is false |

> [!danger] `reorder_threshold = 0` disables the alert entirely
> $q \le 0$ is only true at exactly zero, and only if quantity never goes negative — which the clamps in [[Path — Logging a sale]] and [[Path — Logging wastage]] guarantee. So an item with a zero threshold can hit empty and, strictly, does flag — but only at literal zero, with no warning beforehand. A null threshold behaves the same way (`NULL * 0.5` is `NULL`, and the SQL comparison yields `NULL`, so the row is excluded from the dashboard count while the PHP check treats null as 0). Any item that matters should have a threshold set.

## Where the alert actually fires

**Only** in `LogProduction.php:55-58`, after stock has been added.

Sales and wastage both reduce stock and neither raises the alert. That reads backwards and it is a known gap — an item can sit depleted indefinitely, drained by ordinary service, until the next production batch happens to touch it.

The dashboard is the real safety net:

```php
$lowStockCount = InventoryItem::whereRaw(...)->count();
$lowStockItems = InventoryItem::whereRaw(...)->orderBy('quantity_on_hand')->take(5)->get();
```

Recomputed on every dashboard load, so it is never stale regardless of which write path caused the depletion.

## The notification

Database notification (no queue worker), sent to head chefs, scoped by `is_demo` to the actor's own world — commit `feafeb5`, explained in [[Path — Logging production]] and [[Demo sandbox]].

Renders as a red sidebar banner. Dismissal: `POST /notifications/dismiss-low-stock`, gate `dismiss-low-stock` → `isManager()`. Owners can dismiss too even though they never receive it, because they see the banner. Dismiss only ever marks the current user's own notifications read — no escalation path.

## See also

[[Path — Logging production]] · [[Inventory monetary value]] · [[Inventory as shared state]] · [[Path — Rendering the dashboard]]
