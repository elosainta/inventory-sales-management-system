# Path — Logging a purchase

Stock coming in from a supplier. The only path (with [[Path — Logging production]]) that changes an ingredient's **price**, which is why it triggers a cascade of recipe recosting.

## Trigger

| | |
|---|---|
| Route | `POST /purchases` → `PurchaseController@store` |
| Gate | `manage-purchases` → `isManager()` |
| Action | `app/Domain/Purchasing/Actions/LogPurchase.php` |
| Optional | receipt image upload |

## The line

### 1 · `LogPurchase.php:19` — split the lines off

```php
$lines = $data['lines'] ?? [];
unset($data['lines']);
```

`$data` is about to be mass-assigned to `Purchase::create()`. The line items are not columns on that table, so they must come out first.

### 2 · `LogPurchase` — receipt to the private disk

```php
$data['receipt_path'] = $receipt->store('receipts');
```

> [!note] Private, served through a gate
> Receipts go to the default (**private**) disk, like sale photos, and are served by `PurchaseController@receipt` behind `view-purchases` — never by a public URL. (This note used to say the public disk; that stopped being true when uploads were made private, and `PrivateUploadsTest` pins it.)

### 3 · `LogPurchase.php:25-30` — total the invoice

```php
$total = 0;
foreach ($lines as $line) {
    $total += $line['quantity'] * $line['unit_price'];
}
$data['total_amount'] = round($total, 2);
```

$$\text{total} = \operatorname{round}\!\left(\sum_{i} q_i \cdot p_i,\; 2\right)$$

> [!note] Round once, at the end
> The sum accumulates unrounded and rounds only on the way into the column. Rounding each line first and then summing would let up to half a sen of error per line accumulate into a visible mismatch against the paper invoice. Line totals are rounded separately for display (line 33) — a rounded *display* value, not a rounded *input* to the sum. See [[Rounding and money]].

### 4 · `LogPurchase.php:32-56` — per line: record, then update stock

```php
$item->quantity_on_hand += $line['quantity'];
$item->unit_cost         = $line['unit_price'];
$item->monetary_value    = round($item->quantity_on_hand * $item->unit_cost, 2);
$item->last_updated      = now();
```

Three distinct effects:

- **Quantity accumulates** — `+=`, unlike [[Path — Recording a tally]] which overwrites.
- **`unit_cost` is overwritten, not averaged.** Last price wins. Discussed at length in [[Inventory as shared state]] — this is the single most consequential simplification in the system.
- **`monetary_value` is recomputed** from the *new* quantity and the *new* cost. Order matters: quantity is updated before the multiplication.

### 5 · `LogPurchase.php:59-62` — recost affected recipes

```php
if ($updatedItemIds) {
    Recipe::whereHas('ingredients', fn ($q) => $q->whereIn('inventory_item_id', $updatedItemIds))
        ->each(fn ($recipe) => $recipe->recalculatePlateCost());
}
```

This is the cascade. Because step 4 changed prices, every recipe using any re-priced ingredient now has a stale `plate_cost`. `whereHas` narrows the recost to only affected recipes rather than all of them.

$$\text{recipes to recost} = \{\, r : \exists\, i \in \text{ingredients}(r),\; i \in \text{updated} \,\}$$

Each `recalculatePlateCost()` call is a `save()`, so each also writes an [[Audit trail]] row. A large invoice touching many shared ingredients produces a lot of audit traffic. That is the intended trade — see [[Plate cost]].

## The mathematics

- Invoice total — above
- [[Inventory monetary value]] — recomputed per item
- [[Plate cost]] — recalculated for every affected recipe

## Paid, Not paid yet, and the supplier Upload

A purchase's `status` is stored `completed` or `pending` and **reads "Paid" and "Not paid yet" on every screen** (2026-10-11, the Owner — only the labels changed). The purchases PDF reads the same column as paid/unpaid. It is the kitchen's own record, not Bukku's balance; the two can differ while a delivery is logged but not yet settled.

The Purchase log is one collapsible row per supplier. Inside each, **Upload receipt** (`PurchaseController@complete`, `manage-purchases`) takes one photo — a statement or a payment slip — and marks paid every purchase shown there that is **Not paid yet or has no receipt**:

```php
$open = Purchase::whereIn('id', $request->validated('purchase_ids'))
    ->where(fn ($q) => $q->where('status', 'pending')->orWhereNull('receipt_path'))
    ->get();
// each: status => completed, receipt_path => its own ?? the one shared photo
```

- It posts the ids on screen, so it completes exactly what the reader saw.
- The photo is stored **once** and shared by every purchase that had none (a supplier with daily deliveries would otherwise store the same photo 25 times a month); one that already had a receipt keeps it. So `destroy()` deletes a receipt file **only when no other purchase still points at it**.
- A refused photo is an error toast (`CompletePurchasesRequest::failedValidation`), because the dropdown is collapsed again after the reload and a field error would be invisible.
- The per-row status buttons stay, to undo a wrong Upload.

The "or has no receipt" half was added because scanned invoices used to arrive Completed with no receipt — the button never appeared for them. They are now recorded **Not paid yet** ([[Path — Scanning an invoice]]), and the 51 already on live, every one still owing in Bukku (RM 10,716.87), were set back by migration `2026_10_11_000002`. `PurchaseLogTest` pins the button.

## Market purchases

`LogMarketPurchase` (`app/Domain/Purchasing/Actions/LogMarketPurchase.php`) is the ad-hoc variant: market and one-off buys rather than supplier orders. Same inventory and recosting effects. Differences: status is always Completed (reads "Paid"), and `signed_by` is free text instead of a supplier relation. Gates `view-market-purchases` / `manage-market-purchases`, both `isManager()`.

## What it touches

| Table | Effect |
|---|---|
| `purchases` | one row |
| `purchase_lines` | one row per line |
| `inventory_items` | one update per distinct item |
| `recipes` | `plate_cost` for every recipe using a re-priced item |
| `audits` | one per purchase, per item, per recosted recipe |

## Traps

- **A typo in `unit_price` reprices the ingredient permanently** and silently recosts every recipe using it. There is no history of previous unit costs — the old value survives only in the `audits` before/after payload.
- **Fixed in `4bbaeb0`:** `user_id` was not being set on purchase creation, producing a 500.
- **A receipt shared by an Upload** belongs to several purchases. Delete files through `destroy()`, never by hand, or the others lose theirs.

## See also

[[Path — Logging production]] · [[Path — Saving a recipe]] · [[Inventory as shared state]] · [[Plate cost]]
