# Path — Logging wastage

The shortest write path, and the one the whole product exists for. The Owner's stated problem is that wastage is invisible; this is where it becomes a number.

## Trigger

| | |
|---|---|
| Route | `POST /wastage` → `WastageEntryController@store` |
| Gate | `manage-wastage` → `isManager()` |
| Action | `app/Domain/Wastage/Actions/LogWastage.php` |

## The line

### 1 · `LogWastage.php:13` — `findOrFail`, not `find`

```php
$item = InventoryItem::findOrFail($data['inventory_item_id']);
```

Deliberately different from `LogSale`, which uses `find()` and skips a missing item. Here the item **is** the entry — there is no meaningful wastage record without one, and pricing the loss requires its `unit_cost`. Failing loudly is correct.

### 2 · `LogWastage.php:15-18` — price the loss

```php
$data['cost_lost'] = round(
    $item->unit_cost * $data['quantity_wasted'],
    2
);
```

$$\text{cost lost} = \operatorname{round}(c_u \times q_w,\; 2)$$

> [!note] Priced at the *current* unit cost, not the cost when the item was bought
> `unit_cost` is last-price-wins ([[Inventory as shared state]]). If prices rose since purchase, the loss is valued at the newer, higher price. That overstates historical loss slightly — and understates it if prices fell. Accepted: it needs no per-lot cost tracking and errs toward making wastage look expensive, which serves the purpose of the feature.

The value is **snapshotted** into `wastage_entries.cost_lost`. Later price changes do not rewrite history — see [[Wastage cost and rate]].

### 3 · `LogWastage.php:20` — create the entry

Fires the `created` event → [[Audit trail]] row.

### 4 · `LogWastage.php:22-26` — deduct

```php
$newQty = max(0, $item->quantity_on_hand - $data['quantity_wasted']);
$item->quantity_on_hand = $newQty;
$item->monetary_value   = round($newQty * $item->unit_cost, 2);
```

Same clamp-at-zero as [[Path — Logging a sale]]. `unit_cost` untouched, so no recipe recosting is needed.

## The mathematics

- [[Wastage cost and rate]] — this value, and the monthly rate built from it
- [[Inventory monetary value]] — refreshed

## Reasons

`wastage_entries.reason` drives the dashboard's wastage-by-reason breakdown (`DashboardController.php:66-68`) and the top-5 wasted items list. This is the analytical payload — the Owner's question is not only *how much* was wasted but *why*.

## What it touches

| Table | Effect |
|---|---|
| `wastage_entries` | one row, with `cost_lost` snapshotted |
| `inventory_items` | one update |
| `audits` | two rows |

## Traps

- **No low-stock alert.** Wastage can drive an item below its threshold in silence. Only [[Path — Logging production]] raises the alert. See [[Low stock threshold]].
- **No reversal on edit**, like every other path here.
- **The clamp hides over-recording.** Wasting more than is on hand floors at zero rather than erroring.

## See also

[[Path — Logging a sale]] · [[Wastage cost and rate]] · [[Path — Rendering the dashboard]]
