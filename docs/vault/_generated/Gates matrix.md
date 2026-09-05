---
generated: true
---

> [!warning] Auto-generated — do not edit
> Rewritten by `php artisan vault:sync` on every commit. Put explanation in the curated notes and link here.
> Source: `app/Console/Commands/VaultSync.php` · Back to [[Home]] · [[Vault automation]]

# Gates matrix

All 56 gates from `AppServiceProvider::boot()`, evaluated live against a synthetic user per role.
`?` means the gate needs state a synthetic user does not have. Explained in [[Authorization gates]].

| Gate | Owner | Head Chef | Junior Chef | Admin |
|---|---|---|---|---|
| `decide-leave` | ✅ | — | — | ✅ |
| `decide-rnd` | ✅ | — | — | — |
| `delete-entries` | ✅ | ✅ | — | ✅ |
| `delete-users` | ✅ | — | — | ✅ |
| `dismiss-low-stock` | ✅ | ✅ | — | ✅ |
| `edit-profile` | ✅ | ✅ | ✅ | ✅ |
| `export-feedback-pdf` | ✅ | — | — | ✅ |
| `export-pdf` | ✅ | ✅ | — | ✅ |
| `manage-events` | ✅ | — | — | ✅ |
| `manage-float` | ✅ | ✅ | — | ✅ |
| `manage-inventory` | ✅ | ✅ | — | ✅ |
| `manage-market-purchases` | ✅ | ✅ | — | ✅ |
| `manage-production` | ✅ | ✅ | ✅ | ✅ |
| `manage-purchases` | ✅ | ✅ | — | ✅ |
| `manage-recipes` | ✅ | ✅ | — | ✅ |
| `manage-rnd` | ✅ | ✅ | ✅ | ✅ |
| `manage-sales` | ✅ | ✅ | — | ✅ |
| `manage-sections` | ✅ | ✅ | — | ✅ |
| `manage-stock-take-items` | ✅ | ✅ | — | ✅ |
| `manage-suppliers` | ✅ | ✅ | — | ✅ |
| `manage-support-tickets` | ✅ | — | — | ✅ |
| `manage-user-language` | ✅ | — | — | ✅ |
| `manage-user-passwords` | ✅ | — | — | ✅ |
| `manage-users` | ✅ | — | — | ✅ |
| `manage-wastage` | ✅ | ✅ | — | ✅ |
| `overview-checklist` | ✅ | ✅ | — | ✅ |
| `record-inventory` | ✅ | ✅ | — | ✅ |
| `record-stock-take` | — | ✅ | ✅ | ✅ |
| `record-tally` | — | ✅ | ✅ | ✅ |
| `search-global` | ✅ | ✅ | — | ✅ |
| `submit-feedback` | — | ✅ | ✅ | ✅ |
| `submit-leave` | — | ✅ | ✅ | ✅ |
| `submit-support` | ✅ | ✅ | ✅ | ✅ |
| `toggle-maintenance` | ✅ | — | — | ✅ |
| `use-invoice-scan` | ✅ | ✅ | — | ✅ |
| `view-about` | ✅ | ✅ | ✅ | ✅ |
| `view-audit-log` | ✅ | — | — | ✅ |
| `view-checklist` | ✅ | ✅ | ✅ | ✅ |
| `view-daily-report` | ✅ | ✅ | — | ✅ |
| `view-dashboard` | ✅ | ✅ | — | — |
| `view-feedback` | ✅ | ✅ | ✅ | ✅ |
| `view-inventory` | ✅ | ✅ | ✅ | ✅ |
| `view-leave` | ✅ | ✅ | ✅ | ✅ |
| `view-market-purchases` | ✅ | ✅ | — | ✅ |
| `view-production` | ✅ | ✅ | ✅ | ✅ |
| `view-purchases` | ✅ | ✅ | — | ✅ |
| `view-recipes` | ✅ | ✅ | — | ✅ |
| `view-rnd` | ✅ | ✅ | ✅ | ✅ |
| `view-sales` | ✅ | ✅ | — | ✅ |
| `view-stock-take` | ✅ | ✅ | ✅ | ✅ |
| `view-suppliers` | ✅ | ✅ | — | ✅ |
| `view-support-tickets` | ✅ | — | — | ✅ |
| `view-tally` | ✅ | ✅ | ✅ | ✅ |
| `view-users` | ✅ | — | — | ✅ |
| `view-wastage` | ✅ | ✅ | — | ✅ |
| `write-daily-report` | — | ✅ | — | ✅ |
