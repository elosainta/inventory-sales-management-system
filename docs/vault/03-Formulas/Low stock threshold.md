# Low stock threshold

When an item counts as run out. One predicate, one line, no constant.

> [!note] The name is the old rule
> The file keeps its name because ten notes link to it as `[[Low stock threshold]]`. The rule it describes changed on **2026-09-12**: there is no threshold in it any more.

## Formula

$$\text{empty}(i) \iff q_i \le 0$$

| Symbol | Meaning | Column |
|---|---|---|
| $q_i$ | quantity on hand | `inventory_items.quantity_on_hand` |

## Code

`app/Models/InventoryItem.php`

```php
public function isOutOfStock(): bool
{
    return $this->quantity_on_hand <= 0;
}

/** The same rule in SQL — the dashboard used to hand-write its own. */
public function scopeOutOfStock($query)
{
    return $query->where('quantity_on_hand', '<=', 0);
}
```

## What changed, and why

Until 1.27 the rule was $q_i \le 0.5\,t_i$ — half of `reorder_threshold`, with a `LOW_STOCK_FACTOR` constant and a long argument about alert fatigue. It read that column as a **floor to stay above**.

It is not one. The Owner's own reading (2026-09-12): `reorder_threshold` is the kitchen's **limit — the most of a thing they hold**, not a minimum. A maximum cannot be a depletion trigger, so it no longer feeds the flag at all. The column stays, labelled *Threshold/Limit* on the Inventory page, and is still what the reorder conversation happens around; nothing in the app compares against it any more.

What flags instead is the unambiguous case: **the shelf is empty**. "Low" became "No stock" everywhere it was shown — the Inventory pill, the dashboard tile and list, the PDF, the sidebar banner.

The consequence is honest and worth stating: there is **no early warning**. An item that is nearly gone says nothing; it speaks when it is gone. A depletion forecast would be the thing to build if that hurts, not a resurrected factor.

## One implementation now

The predicate was written twice — PHP for the notification, hand-written SQL in `DashboardController` (twice) bound to the shared constant. The SQL half is now `scopeOutOfStock()`, so the comparison itself lives in one place rather than only the constant being shared.

## Worked example

| Item | $q_i$ | limit $t_i$ | No stock? |
|---|---|---|---|
| Rice | 45 | 50 | no |
| Chicken thigh | 12 | 30 | no — used to flag |
| Prawns | 5 | 10 | no — used to flag |
| Coriander | 0 | 5 | **yes** |
| Salt | 0 | 0 | **yes** — a zero limit no longer hides it |

The last row is the quiet win: an item with no threshold set used to sit outside the dashboard count entirely (`NULL * 0.5` is `NULL`, so the SQL excluded the row while the PHP check treated null as 0 — the two disagreed). Nothing reads the column now, so nothing depends on it being filled in.

## Where the alert fires

**Only** in `LogProduction`, after a batch has consumed stock. Sales and wastage both reduce stock and neither raises it — an item can sit empty until the next batch happens to touch it. The dashboard tile and list are the real safety net; they are recomputed on every load.

The notification class is still `App\Notifications\LowStockAlert`, deliberately: its FQCN is stored in `notifications.type`, so renaming it would hide every alert already unread in a head chef's sidebar. Its message now reads "… has run out — none left on the shelf."

Database notification (no queue worker), sent to head chefs, scoped by `is_demo` to the actor's own world — see [[Path — Logging production]] and [[Demo sandbox]]. Renders as a red sidebar banner. Dismissal: `POST /notifications/dismiss-low-stock`, gate `dismiss-low-stock` → `isManager()`, and it only ever marks the current user's own notifications read.

## See also

[[Path — Logging production]] · [[Inventory monetary value]] · [[Inventory as shared state]] · [[Path — Rendering the dashboard]]
