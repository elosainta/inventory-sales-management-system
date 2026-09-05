# Demo sandbox

Training accounts that can do **anything** — create, edit, delete — and can never touch live data. The isolation is a physically separate database, not a permission check.

## The idea

The first design blocked demo writes with a `PreventDemoWrites` middleware, plus suppressions on the sales gates and the dashboard. That makes a poor training environment: a trainee who cannot log a sale has not learned to log a sale.

The current design gives them a full copy of the system pointed at a different database. **Freedom inside the sandbox; a wall around it.**

`PreventDemoWrites` and the demo-specific sales suppressions have been deleted. The sandbox makes them unnecessary.

## The mechanism

`app/Http/Middleware/UseDemoDatabase.php`, last in the web group (`bootstrap/app.php:19`):

```php
// The user is resolved on the LIVE connection first — is_demo lives there.
if ($user->is_demo) {
    DB::setDefaultConnection('mariadb_demo');
}
```

Two lines. Every Eloquent query after this point hits `isms_demo` instead of `isms`.

> [!note] Order is load-bearing twice over
> **It must run after `auth`** — it needs `auth()->user()`.
> **It must resolve the user on the live connection** — `is_demo` is a column in the live `users` table. Switching first and reading second would look for the user in the sandbox.

Connections: `config/database.php:91-109`. `mariadb_demo` is a copy of the live connection with `DB_DEMO_DATABASE` swapped in. Same host, same credentials, different schema.

## What must stay on live

This is the part that is easy to get wrong.

```env
SESSION_CONNECTION=mariadb
DB_CACHE_CONNECTION=mariadb
DB_CACHE_LOCK_CONNECTION=mariadb
DB_QUEUE_CONNECTION=mariadb
```

`DB::setDefaultConnection()` changes the **default**, so anything that does not name a connection explicitly follows the demo user into the sandbox. Session, cache and queue all use the default unless pinned.

| Unpinned | Breakage |
|---|---|
| Session | the demo user's session row is written to the sandbox; `demo:reset` truncates it; everyone is logged out |
| Cache | maintenance-mode flag written to the sandbox — the real toggle stops working |
| Queue | jobs queued in a database no worker is watching; they vanish on reset |

Pinning is in `.env`, not in code. [[Environment variables]].

## Accounts

Seeded by `DemoUserSeeder`, all `welcome1234`:

| Email | Role |
|---|---|
| `demochef@example.test` | Head-Chef-level |
| `demojunior@example.test` | Junior-Chef-level |

> [!warning] There is no Owner-level demo account
> `demo@example.test` was deleted on 2026-08-22 and removed from the seeder, so seeding
> will not bring it back. Nothing can be demonstrated at Owner level — the financial
> dashboard, the audit log and the money pages — without signing in as the real Owner.
> It could not be deleted from the Users page by anyone: the Owner never sees demo
> accounts, and an Admin is stopped by the escalation guard because it carried the
> `owner` role. It went by migration.

They **see each other's changes** — one shared sandbox, so a training session with two people works. Only Admins see them in the Users list and search.

`is_demo` is orthogonal to `role`: a demo user has a real role *and* the flag. It changes *which database*, not *what they may do*. [[Roles]].

## Where `is_demo` still appears in logic

Only twice, and both are real bug fixes.

### `toggle-maintenance` — commit `fb627fc`

```php
Gate::define('toggle-maintenance',
    fn (User $user) => ($user->isOwner() || $user->isAdmin()) && ! $user->is_demo);
```

> [!danger] The cache is pinned to live, so a demo toggle 503s the real restaurant
> A demo owner pressing Maintenance Mode would write `maintenance_mode = true` into the **live** cache and take down the actual kitchen from inside a training sandbox. The one place the sandbox leaks, closed by a gate.

### Low-stock notification scoping — commit `feafeb5`

`LogProduction.php:74-77`:

```php
$actorIsDemo = (bool) auth()->user()?->is_demo;

$headChefs = User::where('role', User::ROLE_HEAD_CHEF)
    ->where('is_demo', $actorIsDemo)
    ->get();
```

Demo action → demo head chef (in the sandbox `users` table). Real action → real head chefs. CLI and scheduled runs have no actor, so `?->` gives `null`, `(bool) null` is `false`, and they fall through to real head chefs — the correct default.

[[Path — Logging production]].

## Provisioning

The app DB user (`isms`) **cannot create databases**, so this is a one-time root step.

```sql
CREATE DATABASE isms_demo
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON isms_demo.* TO 'appuser'@'%';
FLUSH PRIVILEGES;
```

Then:

```bash
docker compose exec app php artisan demo:reset
```

`app/Console/Commands/DemoReset.php` clones the live schema and data table by table, streamed in batches. If the demo database is unreachable it prints the exact `CREATE DATABASE` + `GRANT` above rather than failing cryptically.

## Resetting

```bash
php artisan demo:reset
```

**Wipes the sandbox and re-clones from live.** This is both how you reset a training environment and how you refresh it after live data changes.

> [!danger] It reads live and writes demo
> The direction is one-way and safe, but it is a truncate-and-copy on the demo side. Anything a trainee built is gone. Announce it before running it during a session.

## Verifying the isolation

```bash
docker compose exec db mariadb -u isms -p -e \
  "SELECT COUNT(*) FROM isms.sales;
   SELECT COUNT(*) FROM isms_demo.sales;"
```

Log in as `demochef@example.test`, create a sale, re-run. Only the demo count moves.

## See also

[[Middleware stack]] · [[Environment variables]] · [[Database overview]] · [[Roles]]
