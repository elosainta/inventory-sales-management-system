# Stock-take movement

The sheet records a movement, not a snapshot. Current stock is read off live inventory, In and Out are what the kitchen did to it, and the balance is what inventory is left holding.

## Formula

$$\text{balance} = \max(0,\ \text{current} + \text{in} - \text{out})$$

`app/Models/StockTakeEntry.php` — `balance()`. The form previews it as you type and `StockTakeController@store` writes it, so the browser and the server cannot drift.

And per inventory item, across the whole sheet:

$$\Delta_j = \sum_{\ell \,\to\, j} \bigl( \text{in}_\ell - \text{out}_\ell \bigr)$$

`StockTakeEntry::netMovement()` — where $\ell \to j$ means "sheet lines whose catalog item links to inventory item $j$".

> [!warning] This replaced carry-forward on 2026-08-26
> The sheet used to mirror paper. Opening was the previous sheet's closing figure copied forward, all four columns were stored exactly as typed, and only Out reached inventory. Nothing validated that the columns reconciled — **deliberately**, because a gap between the arithmetic and the shelf was the finding the Owner wanted.
>
> That trade no longer applies, because there is no longer a typed figure to disagree with. Current stock is read from `inventory_items.quantity_on_hand`, so the arithmetic and the system figure are the same number by construction.
>
> Surfacing a gap between the system and the shelf is now [[Tally variance]]'s job, and always was the better home for it: a tally is a physical count meeting the system figure. This sheet is the ledger of what moved.

## Four decisions

### Current stock and balance are never submitted

`StoreStockTakeRequest` accepts `qty_in` and `qty_out` and nothing else numeric. The controller reads current stock itself and works the balance out itself.

This is a security property, not tidiness. A posted `current_stock` would be a free write of any figure into `inventory_items` by anyone holding `record-stock-take` — which is every junior chef. The form submits what moved; the server owns what it moved *from*.

### The movement is applied as a delta, not as an absolute

```php
$item->quantity_on_hand = StockTakeEntry::balance($before[$item->id], $net[$item->id]);
```

`$before` is read inside `store()`, not carried over from the figure the form was rendered with. A sale logged while the sheet sat open has already come off stock; writing `current + in − out` using the *rendered* figure would silently put it back.

This is the same reason [[Path — Recording a tally]] is described as reconciling rather than moving: a tally deliberately overwrites, a stock-take deliberately does not.

### Lines are netted before anything is written

Two catalog names can point at one inventory item — the catalog is Sam's handwriting, the inventory is the system's list, and they are not one-to-one. Applying the lines one at a time would make the second `save()` overwrite the first line's movement instead of adding to it.

```php
$net[$id] = ($net[$id] ?? 0) + (float) $line['qty_in'] - (float) $line['qty_out'];
```

Covered by `tests/Unit/StockTakeBalanceTest.php` — the netting case is the one that regresses silently, because a single-line sheet works either way.

### The clamp at zero is the honest answer

An Out larger than what is on hand does not mean the chef is wrong. It means the figure inventory carried was already too low — stock had been moved without being logged. Stock stops at zero; it never goes negative.

The balance stored on the line is therefore what inventory *actually holds afterwards*, which on a clamped line is not `current + in − out`. That is intentional: the sheet records what happened to stock, so the figure has to be the real one.

## Worked example — Pantry

Oyster sauce on hand: **2.65**. Two sheet lines happen to point at it (the catalog carries it twice under different names).

| Line | In | Out |
|---|---|---|
| Oyster sauce | 10 | 4 |
| Oyster sauce (btl) | 0 | 1 |

$$\Delta = (10 - 4) + (0 - 1) = +5 \qquad \text{balance} = 2.65 + 5 = 7.65$$

Both lines record `current = 2.65` and `balance = 7.65` — the netted before and after, because that is what the item actually went from and to. `monetary_value` re-derives to $7.65 \times 11.70 = 89.51$ in [[Inventory monetary value]].

A third line, Vinegar at **1.00** with 2.50 out:

$$\text{balance} = \max(0,\ 1.00 - 2.50) = 0$$

Not −1.50. The 1.50 that could not come off is the signal — and it is [[Tally variance]] that will quantify it next time someone counts.

## Count-only lines

A catalog item with no `inventory_item_id` has no live stock behind it, so `current_stock` and `balance` are stored as **null** and it moves nothing. 26 of the 54 Pantry catalog items are count-only.

The show page renders those with a `count only` pill. That is the only place the sheet says which lines reached inventory — before it existed, a count-only line was indistinguishable from one that moved stock. Links are set from the **Deducts from** picker on the catalog screen.

## Open orders

`stock_take_open_orders` — things on order, noted while counting. Only a name is required. No arithmetic, and no effect on stock: an open order reaches inventory when it arrives and someone enters it as **In**.

Unrelated to the [[Path — Logging a sale]] "open order" flag, which is a discounted staff meal. Same two words, different features.

## The other stock count

This is Feature 4. [[Path — Recording a tally]] is Feature 5. Both write to `inventory_items` and both are recorded by chefs and reviewed by the Owner, but they answer different questions:

| | Question | Write |
|---|---|---|
| Stock-take | what moved? | `quantity_on_hand += in − out` |
| Tally | what is actually there? | `quantity_on_hand = counted` |

[[Glossary]] has the side-by-side.

## See also

[[Path — Recording a stock-take]] · [[Tally variance]] · [[Inventory monetary value]] · [[Glossary]] · [[Formulas index]]
