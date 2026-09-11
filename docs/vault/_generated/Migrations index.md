---
generated: true
---

> [!warning] Auto-generated — do not edit
> Rewritten by `php artisan vault:sync` on every commit. Put explanation in the curated notes and link here.
> Source: `app/Console/Commands/VaultSync.php` · Back to [[Home]] · [[Vault automation]]

# Migrations index

86 migrations, oldest first. The schema they build is described in [[Database overview]].

| Date | Migration | Tables touched |
|---|---|---|
| 0001-01-01 | create users table | `users`, `password_reset_tokens`, `sessions` |
| 0001-01-01 | create cache table | `cache`, `cache_locks` |
| 0001-01-01 | create jobs table | `jobs`, `job_batches`, `failed_jobs` |
| 2026-04-27 | create suppliers table | `suppliers` |
| 2026-04-28 | create inventory items table | `inventory_items` |
| 2026-04-28 | create purchases table | `purchases` |
| 2026-04-28 | create wastage entries table | `wastage_entries` |
| 2026-04-28 | create recipes table | `recipes` |
| 2026-04-28 | create sales table | `sales` |
| 2026-04-28 | create float issuances table | `float_issuances` |
| 2026-04-29 | create audits table | `audits` |
| 2026-04-29 | add role to users table | `users` |
| 2026-04-29 | create purchase lines table | `purchase_lines` |
| 2026-04-29 | create recipe ingredients table | `recipe_ingredients` |
| 2026-04-29 | add invoice number to purchases table | `purchases` |
| 2026-04-30 | add receipt path to float issuances table | `float_issuances` |
| 2026-04-30 | remove receipt path from float issuances table | `purchases` |
| 2026-05-09 | create special events table | `special_events` |
| 2026-05-09 | create sections table | `sections` |
| 2026-05-09 | create section tasks table | `section_tasks` |
| 2026-05-09 | add last login at to users table | `users` |
| 2026-05-09 | create notifications table | `notifications` |
| 2026-05-09 | create section checks table | `section_checks` |
| 2026-05-10 | create login histories table | `login_histories` |
| 2026-05-11 | create user invitations table | `user_invitations` |
| 2026-05-11 | create user permissions table | `user_permissions` |
| 2026-05-11 | drop staff id from operational tables | — |
| 2026-05-11 | create appliance checks table | `appliance_checks` |
| 2026-05-11 | add photo path to section checks table | `section_checks` |
| 2026-05-27 | add preferred language to users table | `users` |
| 2026-05-30 | add soft deletes to recipes table | `recipes` |
| 2026-05-30 | add selling price to recipes table | `recipes` |
| 2026-05-30 | create compliance reports table | `compliance_reports` |
| 2026-05-31 | make user id nullable on sections table | `sections` |
| 2026-06-03 | create market purchases table | `market_purchases` |
| 2026-06-03 | create market purchase lines table | `market_purchase_lines` |
| 2026-06-03 | create daily reports table | `daily_reports` |
| 2026-06-21 | add requires photo to section tasks table | `section_tasks` |
| 2026-06-27 | create support tickets table | `support_tickets` |
| 2026-06-28 | add ticket number and type to support tickets | `support_tickets` |
| 2026-07-01 | create production batches table | `production_batches` |
| 2026-07-01 | create production batch lines table | `production_batch_lines` |
| 2026-07-01 | add misc percent to recipes table | `recipes` |
| 2026-07-01 | add pack size to inventory items table | `inventory_items` |
| 2026-07-04 | add is demo to users table | `users` |
| 2026-07-04 | create leave applications table | `leave_applications` |
| 2026-07-04 | create leave attachments table | `leave_attachments` |
| 2026-07-04 | create feedback entries table | `feedback_entries` |
| 2026-07-04 | create feedback attachments table | `feedback_attachments` |
| 2026-07-05 | create stock take items table | `stock_take_items` |
| 2026-07-05 | create stock takes table | `stock_takes` |
| 2026-07-05 | create stock take entries table | `stock_take_entries` |
| 2026-07-05 | create stock take open orders table | `stock_take_open_orders` |
| 2026-07-05 | create inventory tallies table | `inventory_tallies` |
| 2026-07-05 | create inventory tally lines table | `inventory_tally_lines` |
| 2026-07-05 | create sale attachments table | `sale_attachments` |
| 2026-07-19 | fix cheese unit and salad price | — |
| 2026-07-30 | add open order to sales table | `sales` |
| 2026-08-12 | switch preferred language to indonesian | — |
| 2026-08-12 | keep records when a user is deleted | `audits` |
| 2026-08-13 | add item name to sales table | `sales` |
| 2026-08-13 | realign inventory monetary values | — |
| 2026-08-13 | link stock take items to inventory | `stock_take_items` |
| 2026-08-21 | drop retired kitchen stock take items | — |
| 2026-08-22 | drop unused user invitations table | `user_invitations` |
| 2026-08-22 | delete owner demo account | — |
| 2026-08-26 | rename stock take entry columns | `stock_take_entries` |
| 2026-08-26 | add production pipeline columns | `recipes`, `production_batches` |
| 2026-08-27 | add active days to sections table | `sections` |
| 2026-08-29 | convert sausage and sourdough to pieces | — |
| 2026-08-29 | set sun rise egg sourdough to one slice | — |
| 2026-08-29 | drop appliance checks table | `appliance_checks` |
| 2026-08-30 | create invoice scans table | `invoice_scans` |
| 2026-08-30 | fix section checks user delete behaviour | `section_checks` |
| 2026-08-30 | drop viewer default from users role | `users` |
| 2026-09-03 | create rnd entries table | `rnd_entries` |
| 2026-09-03 | add inventory item to rnd entries | `rnd_entries` |
| 2026-09-03 | add recipe to rnd entries | `rnd_entries` |
| 2026-09-03 | add menu name to rnd entries | `rnd_entries` |
| 2026-09-03 | make rnd entries a costing sheet | `rnd_entry_lines`, `rnd_entries` |
| 2026-09-03 | drop invoice number from rnd entries | `rnd_entries` |
| 2026-09-03 | create invoice item aliases table | `invoice_item_aliases` |
| 2026-09-03 | add purchase id to invoice scans | `invoice_scans` |
| 2026-09-03 | add bukku product id to inventory items | `inventory_items` |
| 2026-09-09 | create staff meals table | `staff_meals`, `staff_meal_lines` |
| 2026-09-09 | drop pax from staff meals | `staff_meals` |
