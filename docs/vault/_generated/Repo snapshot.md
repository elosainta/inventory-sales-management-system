---
generated: true
---

> [!warning] Auto-generated — do not edit
> Rewritten by `php artisan vault:sync` on every commit. Put explanation in the curated notes and link here.
> Source: `app/Console/Commands/VaultSync.php` · Back to [[Home]] · [[Vault automation]]

# Repo snapshot

Taken Sat, Oct 10, 2026 1:36 PM.

> [!note] Git position is one commit behind after a hooked run
> The pre-commit hook generates this **before** the commit exists, so during a commit the
> figures below describe the previous one. Run `php artisan vault:sync` by hand to see HEAD.
> Everything outside this section reads the working tree and is always current.

## Position (as of the last commit)

| | |
|---|---|
| Commits | 453 |
| HEAD | `6bc62d2` |
| Branch | `main` |
| Released version | `1.38.7` |

## Size

| Layer | Files |
|---|---|
| Models | 40 |
| Controllers | 36 |
| Form Requests | 37 |
| Domain actions | 12 |
| Middleware | 3 |
| Console commands | 9 |
| Migrations | 94 |
| Blade views | 82 |
| Tests | 54 |

## Last 15 commits

| Date | Hash | Subject |
|---|---|---|
| 2026-10-10 | `6bc62d2` | fix(deploy): purge the Cloudflare cache over IPv4, and stop blaming the token |
| 2026-10-10 | `52532c0` | chore: drop three model methods nothing calls |
| 2026-10-10 | `d998350` | chore(dev): launch both the PHP server and Vite from the preview pane |
| 2026-10-07 | `a23e37f` | fix(sales): stop the sheet losing a service, and say why a save failed |
| 2026-10-07 | `65cd84f` | chore(mirror): scrub the suppliers the 1.38 reports quote |
| 2026-10-07 | `977d6b0` | feat(purchases): the whole report on one page |
| 2026-10-07 | `25111d7` | feat(purchases): a line per supplier, not a list of ingredients |
| 2026-10-07 | `51f5ea4` | feat(purchases): paid/unpaid and a total summary on the PDF |
| 2026-10-07 | `e1e6009` | feat(purchases): a month to a sheet in the PDF, not a page per supplier |
| 2026-10-07 | `c76a3a4` | feat(supplier-bills): one sheet per month in the PDF |
| 2026-10-07 | `35181e3` | feat(supplier-bills): every bill paid and unpaid, by month, with a PDF |
| 2026-10-07 | `5a03899` | feat(invoice-scan): group what is owed by month, then by supplier |
| 2026-10-05 | `c35657f` | fix(deploy): make a failed cache purge name its own cause |
| 2026-10-05 | `f8f3513` | fix(deploy): report why a cache purge failed, not just that it did |
| 2026-10-05 | `d05dae5` | feat(purchasing): apply pack_size when a delivery goes on the shelf |
