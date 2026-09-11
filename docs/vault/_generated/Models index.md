---
generated: true
---

> [!warning] Auto-generated — do not edit
> Rewritten by `php artisan vault:sync` on every commit. Put explanation in the curated notes and link here.
> Source: `app/Console/Commands/VaultSync.php` · Back to [[Home]] · [[Vault automation]]

# Models index

40 Eloquent models. "Audited" means the model carries the `LogsActivity` trait — see [[Audit trail]].
Model names are plain code, not wiki-links: there is no note per model, and 37 dead links would drown the graph.
The models that carry real behaviour are explained in [[Code paths index]] and [[Formulas index]].

| Model | Table | Audited | Relations |
|---|---|---|---|
| `Audit` | `audits` | — | `user` belongsTo, `auditable` morphTo |
| `ComplianceReport` | `compliance_reports` | ✅ | `fromUser` belongsTo, `toUser` belongsTo |
| `DailyReport` | `daily_reports` | ✅ | `user` belongsTo |
| `FeedbackAttachment` | `feedback_attachments` | — | `feedbackEntry` belongsTo |
| `FeedbackEntry` | `feedback_entries` | ✅ | `fromUser` belongsTo, `toUser` belongsTo, `attachments` hasMany |
| `FloatIssuance` | `float_issuances` | ✅ | — |
| `InventoryItem` | `inventory_items` | ✅ | — |
| `InventoryTally` | `inventory_tallies` | ✅ | `counter` belongsTo, `lines` hasMany |
| `InventoryTallyLine` | `inventory_tally_lines` | — | `tally` belongsTo, `item` belongsTo |
| `InvoiceItemAlias` | `invoice_item_aliases` | ✅ | `inventoryItem` belongsTo |
| `InvoiceScan` | `invoice_scans` | ✅ | `purchase` belongsTo, `user` belongsTo |
| `LeaveApplication` | `leave_applications` | ✅ | `user` belongsTo, `decider` belongsTo, `attachments` hasMany |
| `LeaveAttachment` | `leave_attachments` | — | `leaveApplication` belongsTo |
| `LoginHistory` | `login_histories` | — | `user` belongsTo |
| `MarketPurchase` | `market_purchases` | ✅ | `user` belongsTo, `lines` hasMany |
| `MarketPurchaseLine` | `market_purchase_lines` | — | `marketPurchase` belongsTo, `inventoryItem` belongsTo |
| `ProductionBatch` | `production_batches` | ✅ | `user` belongsTo, `recipe` belongsTo, `lines` hasMany |
| `ProductionBatchLine` | `production_batch_lines` | — | `productionBatch` belongsTo, `inventoryItem` belongsTo |
| `Purchase` | `purchases` | ✅ | `supplier` belongsTo, `lines` hasMany |
| `PurchaseLine` | `purchase_lines` | ✅ | `purchase` belongsTo, `inventoryItem` belongsTo |
| `Recipe` | `recipes` | — | `getProfitAttribute` hasMany, `outputInventoryItem` belongsTo |
| `RecipeIngredient` | `recipe_ingredients` | ✅ | `recipe` belongsTo, `inventoryItem` belongsTo |
| `RndEntry` | `rnd_entries` | — | `lines` hasMany, `recipe` belongsTo, `creator` belongsTo, `decider` belongsTo |
| `RndEntryLine` | `rnd_entry_lines` | — | `rndEntry` belongsTo |
| `Sale` | `sales` | ✅ | `getLabelAttribute` belongsTo, `attachments` hasMany |
| `SaleAttachment` | `sale_attachments` | — | `sale` belongsTo |
| `Section` | `sections` | ✅ | `tasks` hasMany |
| `SectionCheck` | `section_checks` | — | `task` belongsTo, `user` belongsTo |
| `SectionTask` | `section_tasks` | ✅ | `section` belongsTo, `checks` hasMany |
| `SpecialEvent` | `special_events` | ✅ | — |
| `StaffMeal` | `staff_meals` | — | `lines` hasMany, `creator` belongsTo |
| `StaffMealLine` | `staff_meal_lines` | — | `staffMeal` belongsTo |
| `StockTake` | `stock_takes` | ✅ | `counter` belongsTo, `entries` hasMany, `openOrders` hasMany |
| `StockTakeEntry` | `stock_take_entries` | — | `stockTake` belongsTo |
| `StockTakeItem` | `stock_take_items` | ✅ | `inventoryItem` belongsTo |
| `StockTakeOpenOrder` | `stock_take_open_orders` | — | `stockTake` belongsTo |
| `Supplier` | `suppliers` | ✅ | — |
| `SupportTicket` | `support_tickets` | — | `isResolved` belongsTo, `resolver` belongsTo |
| `User` | `users` | — | — |
| `WastageEntry` | `wastage_entries` | ✅ | `inventoryItem` belongsTo |
