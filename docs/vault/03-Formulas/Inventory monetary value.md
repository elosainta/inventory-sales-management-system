# Inventory monetary value

The money sitting on the shelf. Computed per item, summed for the dashboard.

## Formula

**Per item:**

$$m_i = \operatorname{round}(q_i \cdot c_i,\; 2)$$

**Total:**

$$V = \sum_i m_i$$

| Symbol | Meaning | Column |
|---|---|---|
| $q_i$ | quantity on hand | `inventory_items.quantity_on_hand` |
| $c_i$ | unit cost | `inventory_items.unit_cost` |
| $m_i$ | **stored**, not computed on read | `inventory_items.monetary_value` |

## A denormalised column

$m_i$ could be `quantity_on_hand * unit_cost` in a SQL expression. Instead it is a real column, and **every write path is responsible for keeping it correct**.

The trade: dashboard reads become a plain `SUM` of one column, and any consumer can sort or filter on stored value. The cost is that six places must remember to update it, and one that forgets creates a silent inconsistency that nothing detects.

## The five write sites

| Path | Quantity change | `unit_cost` change | Code |
|---|---|---|---|
| [[Path — Logging a sale]] | `-= q_i × n` clamped at 0 | no | `LogSale.php:36-37` |
| [[Path — Logging wastage]] | `-= q_w` clamped at 0 | no | `LogWastage.php:24-25` |
| [[Path — Logging a purchase]] | `+= q` | **overwritten** | `LogPurchase.php:48-50` |
| [[Path — Logging production]] | `+= q` | **overwritten** | `LogProduction.php:48-50` |
| [[Path — Recording a tally]] | **`=` counted** | no | `InventoryTallyController.php:93-94` |

Plus `LogMarketPurchase`, which behaves like `LogPurchase`.

> [!danger] Assignment order is load-bearing
> ```php
> $item->quantity_on_hand += $line['quantity'];   // 1. quantity first
> $item->unit_cost         = $line['unit_price']; // 2. then cost
> $item->monetary_value    = round($item->quantity_on_hand * $item->unit_cost, 2); // 3. then the product
> ```
> Computing `monetary_value` before either update would store the old value. Every call site follows this order. It is the kind of thing a refactor breaks silently.

## Dashboard reads

`DashboardController.php:35`

```php
$inventoryValue = InventoryItem::sum('monetary_value');
```

`DashboardController.php:76-77`

```php
InventoryItem::select('category', DB::raw('SUM(monetary_value) as total'))->groupBy('category')->get();
```

$$V_k = \sum_{i \in \text{category } k} m_i$$

Both are **always current** — never month-filtered. Inventory value is a *stock* quantity (a level at a point in time), not a *flow* (a rate over a period). "Inventory value in June" is not a well-defined question. [[Path — Rendering the dashboard]] explains the two-clock split.

## Worked example

| Item | $q_i$ | $c_i$ | $m_i$ |
|---|---|---|---|
| Rice | 45 kg | 4.80 | 216.00 |
| Chicken thigh | 12.5 kg | 14.00 | 175.00 |
| Prawns | 3.2 kg | 48.00 | 153.60 |
| Coconut milk | 20 L | 6.50 | 130.00 |

$$V = 216.00 + 175.00 + 153.60 + 130.00 = \mathbf{674.60}$$

Now a purchase of 10 kg rice at RM 5.20 — a price rise:

$$q = 45 + 10 = 55, \quad c = 5.20, \quad m = \operatorname{round}(55 \times 5.20,\ 2) = 286.00$$

$$V = 286.00 + 175.00 + 153.60 + 130.00 = \mathbf{744.60}$$

> [!note] The whole 45 kg was revalued, not just the new 10 kg
> Buying 10 kg for RM 52 raised total value by RM 70. Because `unit_cost` is last-price-wins, the existing stock is silently marked to the new price. The RM 18 difference is a revaluation, not a purchase. Over a rising-price month this inflates inventory value with no purchase behind it. See [[Inventory as shared state]].

## Drift

Nothing verifies that $m_i = q_i c_i$. If a write path is ever added that updates quantity without refreshing value, the dashboard is quietly wrong.

Check:

```sql
SELECT id, name, quantity_on_hand, unit_cost, monetary_value,
       ROUND(quantity_on_hand * unit_cost, 2) AS expected
FROM inventory_items
WHERE ABS(monetary_value - ROUND(quantity_on_hand * unit_cost, 2)) > 0.01;
```

Repair:

```php
InventoryItem::each(fn ($i) => $i->update([
    'monetary_value' => round($i->quantity_on_hand * $i->unit_cost, 2),
]));
```

That is a bulk `update()`, so it writes an [[Audit trail]] row per item. Intentional — a mass correction should be visible.

## See also

[[Inventory as shared state]] · [[Low stock threshold]] · [[Plate cost]] · [[Rounding and money]]
