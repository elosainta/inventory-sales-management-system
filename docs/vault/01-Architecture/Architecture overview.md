# Architecture overview

A single-tenant Laravel monolith, server-rendered with Blade, deployed as one Docker image onto one DigitalOcean droplet. No API, no SPA, no queue workers doing anything critical, no microservices. For one kitchen with five users, that is the correct amount of machinery.

## Stack as it actually is

| Layer | Reality |
|---|---|
| Framework | Laravel 13.35 on PHP 8.5 (the production image builds on `php:8.5-fpm-alpine`) |
| Database | MariaDB 12 — two databases, see [[Database overview]] |
| Views | **Blade, server-rendered.** 82 `.blade.php` files (2026-10-11) |
| Frontend JS | **None.** Vite + Tailwind build the CSS; interactivity is plain inline `<script>` |
| PDF | DomPDF |
| Charts | Chart.js, from the jsdelivr CDN |
| Package manager | pnpm (not npm) |
| Quality | PHPUnit (452 tests), **Larastan** level 5, **Pint** — [[Local development#Tests]] |
| Delivery | GitHub Actions: a pushed release is checked and deployed — [[Deployment]] |
| Alerts | notified GitHub issues for errors, tickets, outages — [[Monitoring and alerts]] |

> [!note] There is no React any more
> Inertia/React once covered exactly one screen, the login page, and was removed in 1.10.37: it cost 7 npm packages, shipped a 317 KB bundle on every page and threw a `TypeError` on every other page. Look for any page in `resources/views/`. `resources/js/app.js` still loads on **every** page, but only because it imports `resources/css/app.css` — removing it strips all styling.

## The five layers

```
HTTP request
    ↓
routes/web.php ─────────── declares the URL, names it, attaches auth
    ↓
Middleware ────────────── MaintenanceMode → SetLocale → UseDemoDatabase   [[Middleware stack]]
    ↓
Form Request ──────────── validates input before the controller sees it
    ↓
Controller ────────────── Gate::authorize() first line, then delegate      [[Authorization gates]]
    ↓
Domain Action ─────────── the business logic and the writes                [[Layering rules]]
    ↓
Eloquent Model ────────── persistence + the LogsActivity audit hook        [[Audit trail]]
    ↓
Blade view ────────────── @money(), @can(), Chart.js
```

Detail per hop: [[Request lifecycle]].

## Domain contexts

Eight actions across seven contexts. Full generated list: [[Domain actions index]].

| Context | Action | Note |
|---|---|---|
| Sales | `LogSale` | [[Path — Logging a sale]] |
| Purchasing | `LogPurchase`, `LogMarketPurchase` | [[Path — Logging a purchase]] |
| Wastage | `LogWastage` | [[Path — Logging wastage]] |
| Production | `LogProduction` | [[Path — Logging production]] |
| Recipes | `SaveRecipe` | [[Path — Saving a recipe]] |

Two features that write stock have **no** action class and do the work in the controller: tally reconciliation (`InventoryTallyController::store`) and stock-take recording (`StockTakeController::store`). That is an inconsistency worth knowing about, not a bug — see [[Layering rules]].

Events and Float have no action class either, for the opposite reason: `LogEvent` and `IssueFloat` were deleted in 1.10.48. Each was a class whose entire body was `Model::create($data)` — one caller, no transaction, no derived value, nothing the controller could not say in the same line. An action earns its file by holding logic; those two held a namespace.

## The centre of gravity

Almost every write in this system converges on one table.

```
LogSale ────────┐
LogPurchase ────┤
LogMarketPurchase┤
LogProduction ──┼──→  inventory_items  ──→  recipes.plate_cost  ──→  dashboard KPIs
LogWastage ─────┤        (quantity_on_hand,
Tally ──────────┘         unit_cost,
                          monetary_value)
```

Understand [[Inventory as shared state]] and you understand most of the system's coupling.

## What the system deliberately does not do

- **No reversal on edit.** Editing a sale fixes the typed numbers; it never un-deducts the stock. Edits are for data-entry mistakes, not returns.
- **No off-menu revenue.** `sales.recipe_id` is required. Revenue that is not a dish cannot be recorded.
- **No runtime git parsing** for release notes. Commit messages are too technical and the Docker image ships without `.git` — see `app/Support/ReleaseNotes.php`.
- **No queue workers in the critical path.** Notifications go straight to the database.

## See also

[[Layering rules]] · [[Middleware stack]] · [[Database overview]] · [[VPS and hosting]]
