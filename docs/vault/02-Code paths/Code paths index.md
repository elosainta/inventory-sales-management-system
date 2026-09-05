# Code paths index

Each note traces one operation from the HTTP request to the last row written, naming every file and line it passes through and explaining the reasoning at each hop.

## The paths

| Path | Entry | Action | Writes to `inventory_items`? | Formulas |
|---|---|---|---|---|
| [[Path — Logging a sale]] | `POST /sales` | `LogSale` | **deducts** the finished dish, else recipe ingredients | [[Sale revenue]] |
| [[Path — Logging a purchase]] | `POST /purchases` | `LogPurchase` | **adds** + resets `unit_cost` | [[Inventory monetary value]] |
| [[Path — Logging wastage]] | `POST /wastage` | `LogWastage` | **deducts** wasted qty | [[Wastage cost and rate]] |
| [[Path — Logging production]] | `POST /production` | `LogProduction` | **consumes** the recipe, **adds** the finished dish + alerts | [[Plate cost]] |
| [[Path — Saving a recipe]] | `POST /recipes` | `SaveRecipe` | no — reads `unit_cost` | [[Plate cost]] |
| [[Path — Recording a tally]] | `POST /tally` | *(controller)* | **overwrites** counted quantities | [[Tally variance]] |
| [[Path — Recording a stock-take]] | `POST /stock-take` | *(controller)* | **moves** by net In − Out | [[Stock-take movement]] |
| [[Path — Rendering the dashboard]] | `GET /dashboard` | *(read only)* | no | [[Cost of goods sold]], [[Gross margin]] |

## How they connect

```
                       ┌──────────────────────┐
   LogPurchase ───────▶│                      │
   LogMarketPurchase ─▶│    inventory_items   │◀─── Tally (overwrite)
   LogProduction ─────▶│  quantity_on_hand    │
                       │  unit_cost           │
   LogSale ───────────▶│  monetary_value      │
   LogWastage ────────▶│                      │
                       └──────────┬───────────┘
                                  │ unit_cost changed?
                                  ▼
                       recalculatePlateCost()
                                  │
                                  ▼
                       recipes.plate_cost ──────▶ COGS ──▶ Gross margin
                                  │
                                  ▼
                            Dashboard KPIs
```

Every arrow into that box also writes an [[Audit trail]] row, automatically, via the model's `LogsActivity` trait.

The shared-state consequences of this diagram — write ordering, the last-price-wins `unit_cost` rule, why edits do not reverse — are in [[Inventory as shared state]].

## Reading a path note

Each one has the same five sections:

1. **Trigger** — route, gate, form request
2. **The line** — numbered hops with the real code
3. **The mathematics** — links out to [[Formulas index]]
4. **What it touches** — every table written
5. **Traps** — what has actually gone wrong here

## See also

[[Architecture overview]] · [[Layering rules]] · [[Formulas index]] · [[Domain actions index]]
