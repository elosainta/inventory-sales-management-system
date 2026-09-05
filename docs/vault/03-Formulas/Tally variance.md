# Tally variance

The gap between what the system thinks is on the shelf and what is actually there. This number is the entire point of Feature 5.

## Formula

$$v = q_{\text{counted}} - q_{\text{system}}$$

with $v$ undefined (null) when either side is missing.

| Symbol | Meaning | Column |
|---|---|---|
| $q_{\text{counted}}$ | what the chef physically counted | `inventory_tally_lines.counted_quantity` |
| $q_{\text{system}}$ | `quantity_on_hand` **before** reconciliation | `inventory_tally_lines.system_quantity` |

## Code

`app/Models/InventoryTallyLine.php:36-47`

```php
public function variance(): ?float
{
    if ($this->counted_quantity === null || $this->system_quantity === null) {
        return null;
    }

    return (float) $this->counted_quantity - (float) $this->system_quantity;
}
```

Null rather than zero when either side is missing. An ad-hoc item that was never in live inventory has no system figure, and calling that a variance of zero would be a lie — a real match and an unknowable comparison must not render identically.

## Sign convention

**Counted minus system.** So the sign points at reality:

| $v$ | Name | Meaning | Colour |
|---|---|---|---|
| $v < 0$ | short | less on the shelf than recorded | red |
| $v > 0$ | over | more on the shelf than recorded | copper |
| $v = 0$ | match | agreement | green |

Negative is the interesting case — the direction of shrinkage, waste, theft and unrecorded use. Getting the order backwards would invert every colour on the review page.

## Why the snapshot matters

`system_quantity` is written **before** live stock is reconciled, in the same loop:

```php
'system_quantity'  => $item?->quantity_on_hand,   // snapshot: pre-reconcile
'counted_quantity' => $row['counted_quantity'],
// ...then, below:
$item->quantity_on_hand = $row['counted_quantity'];   // reconcile
```

`InventoryTallyController.php:59` loads the items **before** the transaction writes, so those in-memory models hold the pre-reconcile figures.

Without the snapshot, `variance()` would read live stock — which the reconcile just set equal to the count — and **every variance would compute as zero**. The whole record would be self-erasing. The snapshot is what makes history honest.

Same reason `item_name`, `unit` and `category` are snapshotted: renaming or recategorising an item later must not rewrite what the sheet said on the day.

## Worked example

| Item | system | counted | $v$ | Reading |
|---|---|---|---|---|
| Rice | 45.00 | 43.50 | **−1.50** | short — ordinary spillage/prep loss |
| Chicken thigh | 12.50 | 12.50 | **0.00** | match |
| Prawns | 3.20 | 0.00 | **−3.20** | short — all gone, nothing recorded |
| Coconut milk | 20.00 | 24.00 | **+4.00** | over — a delivery was never logged |
| Salt | 8.00 | *blank* | **not counted** | skipped entirely |

Items counted: **4**. Items differing: **3**.

Both counts appear on the review page. The ratio matters: 3 of 4 differing suggests the recording discipline is broken, not that individual items walked.

## No monetary variance

$v$ is a **quantity**, never a money value. There is no $v \times c_u$ anywhere.

Adding it would be one line, and it is arguably the number the Owner actually wants — *"RM 154 of stock is unaccounted for this month"*. The reason it does not exist: valuing a variance implies an accounting claim about *why* the stock is gone. A short prawn count could be theft, spillage, an unlogged staff meal, or a mistyped earlier entry. Reporting quantities keeps the tally a factual record and leaves the interpretation to the Owner.

If it is ever added, use the snapshotted `unit_cost` at count time, not the current one — see the snapshot-versus-live discussion in [[Wastage cost and rate]].

## What happens after

The count wins. `quantity_on_hand` is **overwritten**, and `monetary_value` is refreshed at the new quantity:

$$m_i = \operatorname{round}(q_{\text{counted}} \cdot c_i,\; 2)$$

`unit_cost` is untouched, so [[Plate cost]] is unaffected and no recipe recosting cascade runs. This is the one write to `inventory_items` that skips the cascade — see [[Inventory as shared state]].

The reconciliation is irreversible. The previous quantity survives only in `system_quantity` and in the [[Audit trail]] before/after payload.

## Blank is not zero

$$\text{blank} \Rightarrow \text{skip} \qquad 0 \Rightarrow q_{\text{system}} := 0$$

The filter at `InventoryTallyController.php:48-51` uses `!== ''`, not `empty()`, because `empty('0')` is `true` in PHP and would silently discard every legitimate zero count. Fully explained in [[Path — Recording a tally]].

## See also

[[Path — Recording a tally]] · [[Stock-take movement]] · [[Inventory monetary value]] · [[Audit trail]]
