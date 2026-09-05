# Wastage cost and rate

The number the product exists to produce. The Owner's stated problem is that wastage is invisible.

## Two formulas

**Per entry** — the money value of one thing thrown away:

$$L = \operatorname{round}(c_u \cdot q_w,\; 2)$$

**Per month** — waste as a share of buying:

$$W\% = \begin{cases} \operatorname{round}\!\left(\dfrac{\sum L}{\sum \text{spend}} \times 100,\; 1\right) & \text{spend} > 0 \\[2.5ex] 0 & \text{otherwise}\end{cases}$$

| Symbol | Meaning | Column |
|---|---|---|
| $c_u$ | unit cost at the moment of recording | `inventory_items.unit_cost` |
| $q_w$ | quantity wasted | `wastage_entries.quantity_wasted` |
| $L$ | cost lost, **snapshotted** | `wastage_entries.cost_lost` |
| spend | month's purchase total | `purchases.total_amount` |

## Code

`app/Domain/Wastage/Actions/LogWastage.php:15-18`

```php
$data['cost_lost'] = round(
    $item->unit_cost * $data['quantity_wasted'],
    2
);
```

`app/Http/Controllers/DashboardController.php:43`

```php
$wastageRate = $purchaseSpend > 0 ? round(($wasteCost / $purchaseSpend) * 100, 1) : 0;
```

## Snapshotted, unlike COGS

`cost_lost` is **stored** on the entry. Later price changes never rewrite it. Last March's wastage figure is the same number today as it was in March.

That is the opposite of [[Cost of goods sold]], which recomputes from current `plate_cost` and therefore shifts as prices move. The two are inconsistent, and it is worth knowing which is which:

| | Snapshotted | Recomputed |
|---|---|---|
| Wastage cost | ✅ frozen at write | |
| COGS | | ✅ uses today's plate cost |
| Sale revenue | ✅ frozen at write | |
| Tally system quantity | ✅ frozen at count | |

Wastage is the better-behaved of the two. If historical stability ever matters for COGS, this is the pattern to copy.

## Priced at current cost, not purchase cost

$c_u$ is whatever `unit_cost` happens to be *now*, and `unit_cost` is last-price-wins ([[Inventory as shared state]]). Something bought at RM 20/kg and thrown away after the price rose to RM 30 is recorded as a RM 30/kg loss.

Slightly overstates loss in a rising market, understates it in a falling one. Accepted because it needs no per-lot cost tracking, and erring toward "wastage looks expensive" serves the purpose of the feature.

## Worked example

March wastage:

| Item | $q_w$ | $c_u$ | $L$ |
|---|---|---|---|
| Chicken thigh | 2.5 kg | 14.00 | 35.00 |
| Coriander | 0.8 kg | 12.00 | 9.60 |
| Prawns | 1.2 kg | 48.00 | 57.60 |
| Rice | 3.0 kg | 4.80 | 14.40 |

$$\sum L = 116.60$$

With March purchase spend of RM 4,100.00:

$$W\% = \operatorname{round}\!\left(\frac{116.60}{4100.00} \times 100,\; 1\right) = \operatorname{round}(2.8439\ldots,\ 1) = \mathbf{2.8\%}$$

## Why the denominator is purchase spend

Not revenue, not inventory value.

**The case for spend:** wastage is a fraction of what was bought. "3% of what I bought went in the bin" is directly actionable — it points at ordering.

**The distortion:** the numerator and denominator run on different clocks. Waste something in March that was bought in February, and it lands in March's numerator against March's denominator. A month with light purchasing and heavy waste of old stock shows an inflated rate.

$$W\% \text{ is only meaningful when purchasing is roughly steady month to month.}$$

Spikes in the chart should be checked against `purchaseSpend` before being read as a wastage problem — a month with a small denominator produces a large rate from ordinary waste.

Using revenue as the denominator would be more stable and directly comparable to [[Gross margin]]. Spend was chosen because the Owner's question is about *over-ordering*, and spend is the ordering signal.

## Wastage is outside gross margin

$L$ appears nowhere in [[Gross margin]]. Gross margin only accounts for the cost of food that was **sold**; food binned was never sold.

So the dashboard shows two independent numbers, and they must be read together:

| Gross margin | Wastage rate | Reading |
|---|---|---|
| 60% | 2% | healthy |
| 60% | 15% | pricing is fine, the kitchen is bleeding stock |
| 40% | 2% | tight pricing, disciplined kitchen |

## The `reason` breakdown

`wastage_entries.reason` drives the by-reason chart (`DashboardController.php:66-68`) and the top-5 wasted items list. The Owner's question is not only *how much* but *why* — this is where the answer to over-ordering versus spoilage versus prep error actually lives.

## Traps

- **No low-stock alert** fires when wastage depletes an item. Only [[Path — Logging production]] raises it. See [[Low stock threshold]].
- **The clamp hides over-recording.** Wasting more than is on hand floors stock at zero rather than erroring, so the entry records a loss larger than the stock that existed.
- **Market purchases are not in the denominator.** `purchaseSpend` sums `purchases` only, not `market_purchases`. A kitchen buying heavily at markets shows an inflated wastage rate.

## See also

[[Path — Logging wastage]] · [[Gross margin]] · [[Inventory as shared state]] · [[Path — Rendering the dashboard]]
