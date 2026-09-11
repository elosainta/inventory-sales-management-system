---
generated: true
---

> [!warning] Auto-generated — do not edit
> Rewritten by `php artisan vault:sync` on every commit. Put explanation in the curated notes and link here.
> Source: `app/Console/Commands/VaultSync.php` · Back to [[Home]] · [[Vault automation]]

# Routes

All 135 registered routes, read straight from Laravel's router.
Every controller method behind these is required to open with `Gate::authorize()` — see [[Authorization gates]].

| Method | URI | Name | Action | Middleware |
|---|---|---|---|---|
| `DELETE` | `/events/{event}` | `events.destroy` | `SpecialEventController@destroy` | auth |
| `DELETE` | `/float/{floatIssuance}` | `float.destroy` | `FloatIssuanceController@destroy` | auth |
| `DELETE` | `/inventory/{inventoryItem}` | `inventory.destroy` | `InventoryItemController@destroy` | auth |
| `DELETE` | `/invoice-scan/{invoiceScan}` | `invoice-scan.destroy` | `InvoiceScanController@destroy` | auth |
| `DELETE` | `/market-purchases/{marketPurchase}` | `market-purchases.destroy` | `MarketPurchaseController@destroy` | auth |
| `DELETE` | `/production/{productionBatch}` | `production.destroy` | `ProductionController@destroy` | auth |
| `DELETE` | `/profile` | `profile.destroy` | `ProfileController@destroy` | auth |
| `DELETE` | `/purchases/{purchase}` | `purchases.destroy` | `PurchaseController@destroy` | auth |
| `DELETE` | `/recipes/{recipe}` | `recipes.destroy` | `RecipeController@destroy` | auth |
| `DELETE` | `/rnd/{rndEntry}` | `rnd.destroy` | `RndEntryController@destroy` | auth |
| `DELETE` | `/sales/{sale}` | `sales.destroy` | `SaleController@destroy` | auth |
| `DELETE` | `/sections/{section}/tasks/{task}` | `sections.tasks.destroy` | `SectionController@destroyTask` | auth |
| `DELETE` | `/sections/{section}` | `sections.destroy` | `SectionController@destroy` | auth |
| `DELETE` | `/staff-meals/{staffMeal}` | `staff-meals.destroy` | `StaffMealController@destroy` | auth |
| `DELETE` | `/stock-take/items/{stockTakeItem}` | `stock-take-items.destroy` | `StockTakeItemController@destroy` | auth |
| `DELETE` | `/suppliers/{supplier}` | `suppliers.destroy` | `SupplierController@destroy` | auth |
| `DELETE` | `/users/{user}` | `users.destroy` | `UserController@destroy` | auth |
| `DELETE` | `/wastage/{wastageEntry}` | `wastage.destroy` | `WastageEntryController@destroy` | auth |
| `GET` | `/` | — | `Closure` | — |
| `GET` | `/about` | `about.index` | `AboutController@index` | auth |
| `GET` | `/audit-log` | `audits.index` | `AuditController@index` | auth |
| `GET` | `/daily-report` | `daily-report.index` | `DailyReportController@index` | auth |
| `GET` | `/dashboard` | `dashboard` | `DashboardController@index` | auth |
| `GET` | `/events/export/pdf` | `events.export-pdf` | `SpecialEventController@exportPdf` | auth |
| `GET` | `/events` | `events.index` | `SpecialEventController@index` | auth |
| `GET` | `/feedback/attachments/{feedbackAttachment}` | `feedback.attachment` | `FeedbackController@attachment` | auth |
| `GET` | `/feedback/export/pdf` | `feedback.export-pdf` | `FeedbackController@exportPdf` | auth |
| `GET` | `/feedback` | `feedback.index` | `FeedbackController@index` | auth |
| `GET` | `/float` | `float.index` | `FloatIssuanceController@index` | auth |
| `GET` | `/inventory/export/pdf` | `inventory.export-pdf` | `InventoryItemController@exportPdf` | auth |
| `GET` | `/inventory` | `inventory.index` | `InventoryItemController@index` | auth |
| `GET` | `/invoice-scan/{invoiceScan}/photo` | `invoice-scan.photo` | `InvoiceScanController@photo` | auth |
| `GET` | `/invoice-scan/{invoiceScan}` | `invoice-scan.show` | `InvoiceScanController@show` | auth |
| `GET` | `/invoice-scan` | `invoice-scan.index` | `InvoiceScanController@index` | auth |
| `GET` | `/leave/attachments/{leaveAttachment}` | `leave.attachment` | `LeaveApplicationController@attachment` | auth |
| `GET` | `/leave` | `leave.index` | `LeaveApplicationController@index` | auth |
| `GET` | `/login-history` | `login-history.index` | `LoginHistoryController@index` | auth |
| `GET` | `/login` | `login` | `Auth\AuthenticatedSessionController@create` | guest, cache.headers:no_store |
| `GET` | `/market-purchases/{marketPurchase}/receipt` | `market-purchases.receipt` | `MarketPurchaseController@receipt` | auth |
| `GET` | `/market-purchases` | `market-purchases.index` | `MarketPurchaseController@index` | auth |
| `GET` | `/prep/overview` | `prep.overview` | `PrepChecklistController@overview` | auth |
| `GET` | `/prep/task-check/{sectionCheck}/photo` | `prep.task-check.photo` | `PrepChecklistController@taskCheckPhoto` | auth |
| `GET` | `/prep` | `prep.index` | `PrepChecklistController@index` | auth |
| `GET` | `/production/dish/{recipe}` | `production.dish` | `ProductionController@dish` | auth |
| `GET` | `/production` | `production.index` | `ProductionController@index` | auth |
| `GET` | `/profile` | `profile.edit` | `ProfileController@edit` | auth |
| `GET` | `/purchases/export/pdf` | `purchases.export-pdf` | `PurchaseController@exportPdf` | auth |
| `GET` | `/purchases/{purchase}/receipt` | `purchases.receipt` | `PurchaseController@receipt` | auth |
| `GET` | `/purchases` | `purchases.index` | `PurchaseController@index` | auth |
| `GET` | `/recipes/export/pdf` | `recipes.export-pdf` | `RecipeController@exportPdf` | auth |
| `GET` | `/recipes/{recipe}` | `recipes.show` | `RecipeController@show` | auth |
| `GET` | `/recipes` | `recipes.index` | `RecipeController@index` | auth |
| `GET` | `/rnd/export/pdf` | `rnd.export-pdf` | `RndEntryController@exportPdf` | auth |
| `GET` | `/rnd` | `rnd.index` | `RndEntryController@index` | auth |
| `GET` | `/sales/attachments/{saleAttachment}` | `sales.attachment` | `SaleController@attachment` | auth |
| `GET` | `/sales/export/pdf` | `sales.export-pdf` | `SaleController@exportPdf` | auth |
| `GET` | `/sales` | `sales.index` | `SaleController@index` | auth |
| `GET` | `/search` | `search.index` | `SearchController@index` | auth |
| `GET` | `/sections` | `sections.index` | `SectionController@index` | auth |
| `GET` | `/staff-meals/export/pdf` | `staff-meals.export-pdf` | `StaffMealController@exportPdf` | auth |
| `GET` | `/staff-meals` | `staff-meals.index` | `StaffMealController@index` | auth |
| `GET` | `/stock-take/create` | `stock-take.create` | `StockTakeController@create` | auth |
| `GET` | `/stock-take/items` | `stock-take-items.index` | `StockTakeItemController@index` | auth |
| `GET` | `/stock-take/{stockTake}` | `stock-take.show` | `StockTakeController@show` | auth |
| `GET` | `/stock-take` | `stock-take.index` | `StockTakeController@index` | auth |
| `GET` | `/storage/{path}` | `storage.local` | `Closure` | — |
| `GET` | `/suppliers` | `suppliers.index` | `SupplierController@index` | auth |
| `GET` | `/support-tickets/{ticket}/media` | `support-tickets.media` | `SupportTicketController@media` | auth |
| `GET` | `/support-tickets` | `support-tickets.index` | `SupportTicketController@index` | auth |
| `GET` | `/support` | `support.index` | `SupportController@index` | auth |
| `GET` | `/tally/create` | `tally.create` | `InventoryTallyController@create` | auth |
| `GET` | `/tally/{inventoryTally}` | `tally.show` | `InventoryTallyController@show` | auth |
| `GET` | `/tally` | `tally.index` | `InventoryTallyController@index` | auth |
| `GET` | `/up` | — | `Closure` | — |
| `GET` | `/users` | `users.index` | `UserController@index` | auth |
| `GET` | `/wastage/export/pdf` | `wastage.export-pdf` | `WastageEntryController@exportPdf` | auth |
| `GET` | `/wastage` | `wastage.index` | `WastageEntryController@index` | auth |
| `PATCH` | `/inventory/{inventoryItem}` | `inventory.update` | `InventoryItemController@update` | auth |
| `PATCH` | `/leave/{leaveApplication}/approve` | `leave.approve` | `LeaveApplicationController@approve` | auth |
| `PATCH` | `/leave/{leaveApplication}/reject` | `leave.reject` | `LeaveApplicationController@reject` | auth |
| `PATCH` | `/profile` | `profile.update` | `ProfileController@update` | auth |
| `PATCH` | `/purchases/{purchase}/toggle-status` | `purchases.toggle-status` | `PurchaseController@toggleStatus` | auth |
| `PATCH` | `/purchases/{purchase}` | `purchases.update` | `PurchaseController@update` | auth |
| `PATCH` | `/rnd/{rndEntry}/approve` | `rnd.approve` | `RndEntryController@approve` | auth |
| `PATCH` | `/rnd/{rndEntry}/reject` | `rnd.reject` | `RndEntryController@reject` | auth |
| `PATCH` | `/rnd/{rndEntry}` | `rnd.update` | `RndEntryController@update` | auth |
| `PATCH` | `/sales/{sale}` | `sales.update` | `SaleController@update` | auth |
| `PATCH` | `/sections/{section}/tasks/{task}` | `sections.tasks.update` | `SectionController@updateTask` | auth |
| `PATCH` | `/sections/{section}` | `sections.update` | `SectionController@update` | auth |
| `PATCH` | `/staff-meals/{staffMeal}` | `staff-meals.update` | `StaffMealController@update` | auth |
| `PATCH` | `/stock-take/items/{stockTakeItem}` | `stock-take-items.update` | `StockTakeItemController@update` | auth |
| `PATCH` | `/suppliers/{supplier}` | `suppliers.update` | `SupplierController@update` | auth |
| `PATCH` | `/support-tickets/{ticket}/toggle` | `support-tickets.toggle` | `SupportTicketController@toggleStatus` | auth |
| `PATCH` | `/support-tickets/{ticket}/type` | `support-tickets.type` | `SupportTicketController@updateType` | auth |
| `PATCH` | `/users/{user}/language` | `users.update-language` | `UserController@updateLanguage` | auth |
| `PATCH` | `/users/{user}/password` | `users.update-password` | `UserController@updatePassword` | auth |
| `PATCH` | `/users/{user}` | `users.update` | `UserController@update` | auth |
| `PATCH` | `/wastage/{wastageEntry}` | `wastage.update` | `WastageEntryController@update` | auth |
| `POST` | `/daily-report` | `daily-report.store` | `DailyReportController@store` | auth |
| `POST` | `/events` | `events.store` | `SpecialEventController@store` | auth |
| `POST` | `/feedback` | `feedback.store` | `FeedbackController@store` | auth, throttle:10,1 |
| `POST` | `/float` | `float.store` | `FloatIssuanceController@store` | auth |
| `POST` | `/inventory` | `inventory.store` | `InventoryItemController@store` | auth |
| `POST` | `/invitations` | `invitations.store` | `InvitationController@store` | auth |
| `POST` | `/invoice-scan/{invoiceScan}/push` | `invoice-scan.push` | `InvoiceScanController@push` | auth, throttle:20,1 |
| `POST` | `/invoice-scan` | `invoice-scan.store` | `InvoiceScanController@store` | auth, throttle:10,1 |
| `POST` | `/leave` | `leave.store` | `LeaveApplicationController@store` | auth, throttle:10,1 |
| `POST` | `/login` | — | `Auth\AuthenticatedSessionController@store` | guest, cache.headers:no_store |
| `POST` | `/logout` | `logout` | `Auth\AuthenticatedSessionController@destroy` | auth |
| `POST` | `/maintenance/toggle` | `maintenance.toggle` | `MaintenanceModeController@toggle` | auth |
| `POST` | `/market-purchases` | `market-purchases.store` | `MarketPurchaseController@store` | auth |
| `POST` | `/notifications/dismiss-low-stock` | `notifications.dismiss-low-stock` | `Closure` | auth |
| `POST` | `/prep/task-check` | `prep.task-check` | `PrepChecklistController@storeTaskCheck` | auth |
| `POST` | `/production/dish/{recipe}` | `production.dish.store` | `ProductionController@storeDish` | auth |
| `POST` | `/purchases` | `purchases.store` | `PurchaseController@store` | auth |
| `POST` | `/recipes/{id}/restore` | `recipes.restore` | `RecipeController@restore` | auth |
| `POST` | `/recipes` | `recipes.store` | `RecipeController@store` | auth |
| `POST` | `/resend/webhook` | `resend.webhook` | `Resend\Laravel\Http\Controllers\WebhookController@handleWebhook` | — |
| `POST` | `/rnd/{rndEntry}/recipe` | `rnd.recipe` | `RndEntryController@createRecipe` | auth |
| `POST` | `/rnd` | `rnd.store` | `RndEntryController@store` | auth |
| `POST` | `/sales/sheet` | `sales.sheet` | `SaleController@storeSheet` | auth |
| `POST` | `/sales/{sale}/restore` | `sales.restore` | `SaleController@restore` | auth |
| `POST` | `/sales` | `sales.store` | `SaleController@store` | auth |
| `POST` | `/sections/{section}/tasks` | `sections.tasks.store` | `SectionController@storeTask` | auth |
| `POST` | `/sections` | `sections.store` | `SectionController@store` | auth |
| `POST` | `/staff-meals` | `staff-meals.store` | `StaffMealController@store` | auth |
| `POST` | `/stock-take/items` | `stock-take-items.store` | `StockTakeItemController@store` | auth |
| `POST` | `/stock-take` | `stock-take.store` | `StockTakeController@store` | auth, throttle:20,1 |
| `POST` | `/suppliers` | `suppliers.store` | `SupplierController@store` | auth |
| `POST` | `/support` | `support.store` | `SupportController@store` | auth, throttle:3,1 |
| `POST` | `/tally` | `tally.store` | `InventoryTallyController@store` | auth, throttle:20,1 |
| `POST` | `/wastage` | `wastage.store` | `WastageEntryController@store` | auth |
| `PUT` | `/password` | `password.update` | `Auth\PasswordController@update` | auth |
| `PUT` | `/recipes/{recipe}` | `recipes.update` | `RecipeController@update` | auth |
| `PUT` | `/storage/{path}` | `storage.local.upload` | `Closure` | — |
