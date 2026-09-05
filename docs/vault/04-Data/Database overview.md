# Database overview

MariaDB 12. **Two physically separate databases** on the same server, plus a handful of Laravel-owned tables.

Generated companions: [[Models index]] · [[Migrations index]]

## The two databases

| Connection | Database | Purpose |
|---|---|---|
| `mariadb` | `isms` | live production data |
| `mariadb_demo` | `isms_demo` | training sandbox, identical schema |

Same host, same credentials, different schema name. `config/database.php:91-109` defines `mariadb_demo` as a copy of the live connection with `DB_DEMO_DATABASE` swapped in.

Which one a request uses is decided per request by the `UseDemoDatabase` middleware. Full mechanics, including why session/cache/queue stay pinned to live: [[Demo sandbox]].

> [!note] Port 3307 locally, 3306 in Docker
> The local dev database runs on **3307** to avoid colliding with an existing MySQL. Inside Docker Compose the app reaches `db:3306` on the internal network. [[Local development]].

## Table families

### Financial — audited, money-bearing

`sales` · `sale_attachments` · `purchases` · `purchase_lines` · `market_purchases` · `market_purchase_lines` · `wastage_entries` · `float_issuances` · `production_batches` · `production_batch_lines` · `recipes` · `recipe_ingredients` · `inventory_items`

Every parent here carries `LogsActivity`. Line tables generally do not — they are child detail, covered by the parent's audit. [[Audit trail]].

### Counting — the two separate systems

| | Tables | Touches live stock |
|---|---|---|
| Stock-take (Feature 4) | `stock_take_items`, `stock_takes`, `stock_take_entries`, `stock_take_open_orders` | **yes â moves by In â Out** |
| Tally (Feature 5) | `inventory_tallies`, `inventory_tally_lines` | **yes — overwrites** |

[[Path — Recording a stock-take]] · [[Path — Recording a tally]]

### People & operations

`users` · `user_permissions` (orphaned — the viewer role that wrote it was deleted in 1.10.48; the empty table is kept rather than dropped) · `login_histories` · `sections` · `section_tasks` · `section_checks` · `appliance_checks` · `daily_reports` · `leave_applications` · `leave_attachments` · `feedback_entries` · `feedback_attachments` · `support_tickets` · `special_events` · `suppliers`

### Retained but dead

`compliance_reports` — the Compliance module was retired and replaced by peer feedback. The table and `ComplianceReport` model are **kept deliberately** so historical data survives and stays visible in the audit log. Do not drop it.

### Laravel-owned

`users`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications`, `password_reset_tokens`.

`notifications` carries the low-stock alerts ([[Low stock threshold]]). `cache` holds the maintenance-mode flag ([[Middleware stack]]).

## Conventions

**Money** is `DECIMAL(12,2)`, never float. [[Rounding and money]].

**Snapshot columns** — denormalised on purpose, so history survives later edits:

| Column | Table | Preserves |
|---|---|---|
| `item_name`, `unit`, `category` | `inventory_tally_lines` | the name as counted |
| `system_quantity` | `inventory_tally_lines` | stock **before** reconcile — [[Tally variance]] |
| `item_name`, `unit` | `stock_take_entries` | survives catalog edits |
| `cost_lost` | `wastage_entries` | the loss at the price of the day |
| `total_revenue` | `sales` | net of discount at time of sale |

`recipes.plate_cost` is the notable **counter**-example: mutable, no history, recomputed as prices move. Consequences in [[Cost of goods sold]].

**Denormalised aggregate:** `inventory_items.monetary_value` is stored, not computed. Five write paths must keep it right. [[Inventory monetary value]].

**Timestamps:** standard `created_at`/`updated_at` except `inventory_tally_lines`, which sets `$timestamps = false` and inherits the parent's `counted_on`.

**Soft deletes:** only `recipes`.

## Migrations

58 files, `2026_04_27` to `2026_07_30`. Full list: [[Migrations index]].

Two are notable as precedent:

- `2026_05_11_091433_drop_staff_id_from_operational_tables.php` — the Staff module was removed; Users covers team management now.
- `2026_07_19_101154_fix_cheese_unit_and_salad_price.php` — a **data** migration, not a schema one. Fixing production data through a migration is the established pattern here: it is versioned, reviewable, and runs automatically on deploy.

## Operations

```bash
php artisan migrate                              # local
docker compose exec app php artisan migrate --force   # production (run by scripts/deploy.sh)
docker compose exec app php artisan demo:reset        # wipe + re-clone the sandbox
```

Backups: `scripts/backup/` pulls a gzipped dump every 5 minutes. [[Backups]].

## See also

[[Demo sandbox]] · [[Audit trail]] · [[Inventory as shared state]] · [[Environment variables]]
