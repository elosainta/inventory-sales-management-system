# Inventory as shared state

`inventory_items` is the table almost everything writes to. Understand this note and you understand most of the system's coupling — and most of its surprises.

```
LogSale ─────────────┐  (deduct)
LogWastage ──────────┤  (deduct)
LogPurchase ─────────┤  (add + reprice)
LogMarketPurchase ───┼──▶  inventory_items  ──reprice?──▶  recipes.plate_cost
LogProduction ───────┤  (add + reprice)                          │
Tally reconcile ─────┘  (overwrite)                              ▼
                                                        COGS ─▶ Gross margin
```

## Three columns, different rules

| Column | Who changes it | How |
|---|---|---|
| `quantity_on_hand` | all six paths | `+=`, `-=` clamped at 0, or `=` (tally only) |
| `unit_cost` | purchases and production only | **overwritten** — last price wins |
| `monetary_value` | all six paths | recomputed as $q \times c$ — [[Inventory monetary value]] |

## Last price wins

The single most consequential simplification in the codebase.

```php
$item->unit_cost = $line['unit_price'];
```

No weighted average, no FIFO, no per-lot costing.

$$c_i^{\text{new}} = p_{\text{latest}} \qquad\text{not}\qquad \frac{q_{\text{old}} c_{\text{old}} + q_{\text{new}} p_{\text{new}}}{q_{\text{old}} + q_{\text{new}}}$$

### What follows from it

**Existing stock is silently revalued.** 45 kg of rice at RM 4.80 is worth RM 216. Buy 10 kg at RM 5.20 and inventory value becomes RM 286 — up RM 70 for a RM 52 purchase. The extra RM 18 is a revaluation of stock you already had. Worked through in [[Inventory monetary value]].

**Every recipe using that ingredient is recosted.** So [[Plate cost]] changes without anyone editing the recipe, and [[Gross margin]] moves without anyone changing a price.

**Wastage is priced at today's cost.** Something bought cheap and binned after a price rise is recorded as the larger loss. [[Wastage cost and rate]].

**Historical COGS is not stable.** March's COGS, recomputed in August, uses August's plate costs. [[Cost of goods sold]].

**One typo propagates everywhere.** A mistyped `unit_price` reprices the ingredient permanently, recosts every recipe using it, and shifts margin. The previous value survives only in the [[Audit trail]] before/after payload.

### Why it is the right call anyway

Weighted average needs lot tracking, which needs a `stock_lots` table, receipt-to-lot mapping, and a consumption policy at every deduction. That is a materially larger system for one kitchen. Last-price-wins answers *"what would it cost me to make this dish today?"* — which is the Owner's actual question — with one assignment.

It is a documented approximation, not an accident. If it ever needs upgrading, the entry point is the three `$item->unit_cost = …` lines.

## Clamped at zero

```php
$newQty = max(0, $item->quantity_on_hand - $deduct);
```

In [[Path — Logging a sale]] and [[Path — Logging wastage]]. Negative stock would poison `monetary_value`, inventory value, and every downstream figure.

The cost: **oversells become invisible.** Selling 50 plates with ingredients for 30 floors the stock at zero and reports nothing. The discrepancy only surfaces at the next [[Path — Recording a tally]] — which is, in fairness, exactly what that feature is for.

## Tally is the exception on two counts

Alone among the six paths, tally:

1. **assigns** rather than adjusts — `$item->quantity_on_hand = $counted`
2. **skips the recost cascade** — it leaves `unit_cost` untouched, so no recipe needs recomputing

Both follow from what counting means: the shelf tells you *how many*, not *what they cost*. [[Path — Recording a tally]].

## The recost cascade

```php
Recipe::whereHas('ingredients', fn ($q) => $q->whereIn('inventory_item_id', $updatedItemIds))
    ->each(fn ($recipe) => $recipe->recalculatePlateCost());
```

$$\text{recost} = \{\, r : \exists\, i \in \text{ingredients}(r),\; i \in \text{updated} \,\}$$

Two call sites: `LogPurchase.php:60-61` and `LogProduction.php:63-64`. `whereHas` narrows to affected recipes rather than recosting all of them.

Each `recalculatePlateCost()` is a `save()`, so each writes an audit row. A 20-line invoice touching shared staples can produce dozens. Intended — a price change *is* a financially significant event.

## No reversal on edit

None of the six paths reverse on edit. `SaleController@update` recalculates revenue but never un-deducts stock.

Editing is for **data-entry typos**, not for returns or cancellations. A genuine correction goes through a tally.

This is a stated product decision, not an oversight. The alternative — reversing and reapplying deductions on every edit — needs the original values, a reversal path, and a guard against double-reversal, in exchange for a case that barely occurs in a five-person kitchen.

## Concurrency

Every path is wrapped in `DB::transaction()`, but there is **no row locking**. Two simultaneous sales of the same dish both read `quantity_on_hand`, both subtract, and the second write wins — one deduction is lost.

At five users on one kitchen's traffic this will effectively never happen. If it ever matters, `lockForUpdate()` on the `InventoryItem` fetch inside each transaction is the fix.

## Audit volume

Every write here fires `LogsActivity`. One sale of a six-ingredient dish writes seven audit rows: the sale plus six inventory updates.

The `audits` table grows fast and has no retention policy. Worth watching: `SELECT COUNT(*) FROM audits`. [[Audit trail]].

## See also

[[Inventory monetary value]] · [[Plate cost]] · [[Code paths index]] · [[Audit trail]]
