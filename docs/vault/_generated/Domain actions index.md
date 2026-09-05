---
generated: true
---

> [!warning] Auto-generated — do not edit
> Rewritten by `php artisan vault:sync` on every commit. Put explanation in the curated notes and link here.
> Source: `app/Console/Commands/VaultSync.php` · Back to [[Home]] · [[Vault automation]]

# Domain actions index

8 actions under `app/Domain/<Context>/Actions/`. Controllers stay thin and delegate here — see [[Layering rules]].
"Transactional" means the whole write is wrapped in `DB::transaction()`.

> [!note] Two features write stock from a controller instead
> Tally reconciliation and stock-take recording have no action class. Both are still transactional. See [[Layering rules]].

| Action | Context | File | Transactional | Walkthrough | Purpose |
|---|---|---|---|---|---|
| `LogProduction` | Production | `app/Domain/Production/Actions/LogProduction.php` | ✅ | [[Path — Logging production]] | The middle of Inventory -> Production -> Sales. |
| `LogMarketPurchase` | Purchasing | `app/Domain/Purchasing/Actions/LogMarketPurchase.php` | ✅ | [[Path — Logging a purchase]] | — |
| `LogPurchase` | Purchasing | `app/Domain/Purchasing/Actions/LogPurchase.php` | ✅ | [[Path — Logging a purchase]] | — |
| `PushInvoiceToBukku` | Purchasing | `app/Domain/Purchasing/Actions/PushInvoiceToBukku.php` | — | — | Turns a reviewed scan into a purchase bill in Bukku. |
| `ScanInvoice` | Purchasing | `app/Domain/Purchasing/Actions/ScanInvoice.php` | — | — | Reads an uploaded invoice photo and writes down what it says. |
| `SaveRecipe` | Recipes | `app/Domain/Recipes/Actions/SaveRecipe.php` | ✅ | [[Path — Saving a recipe]] | Create a recipe with its ingredients, then compute plate cost. |
| `LogSale` | Sales | `app/Domain/Sales/Actions/LogSale.php` | ✅ | [[Path — Logging a sale]] | — |
| `LogWastage` | Wastage | `app/Domain/Wastage/Actions/LogWastage.php` | ✅ | [[Path — Logging wastage]] | — |
