# Roles

Four role strings on `users.role`, constants at the top of `app/Models/User.php`.

```php
public const ROLE_OWNER       = 'owner';
public const ROLE_HEAD_CHEF   = 'head_chef';
public const ROLE_JUNIOR_CHEF = 'junior_chef';
public const ROLE_ADMIN       = 'admin';    // support operator, no kitchen access
```

There was a fifth, `viewer`, deleted in 1.10.48. See [[#The deleted Viewer role]].

## The people

| Role | Count | Sees |
|---|---|---|
| **Owner** | 1 | everything financial: dashboard, audit log, users, sections, reports, all money |
| **Head Chef** | 2 | everything operational: prep overview, purchases, sales, inventory, writes the daily report |
| **Junior Chef** | 3 | every section's prep checklist and appliance photo checks, production batches, stock-take and tally. Reads inventory (cost columns included) but cannot change it. No dashboard, no search |
| **Admin** | 1 | support tickets and user password resets. **No kitchen or financial access at all** |

## Predicates

`User.php:45-84`. These, not string comparisons, are what gates call.

```php
public function isManager(): bool   // Owner OR Head Chef — the workhorse
{
    return in_array($this->role, [self::ROLE_OWNER, self::ROLE_HEAD_CHEF]);
}

public function isOwner(): bool     { return $this->role === self::ROLE_OWNER; }
public function isHeadChef(): bool  { return $this->role === self::ROLE_HEAD_CHEF; }
public function isJuniorChef(): bool{ return $this->role === self::ROLE_JUNIOR_CHEF; }
public function isAdmin(): bool     { return $this->role === self::ROLE_ADMIN; }
```

> [!note] There are two Head Chefs
> Sam and Jordan. This is why the daily report is first-come-first-owned rather than
> one-per-role: whoever writes today's report owns it, and the second is shown it
> read-only instead of silently overwriting it.

**`isManager()` is the most-used predicate in the system.** Most operational gates are exactly `fn ($user) => $user->isManager()`.

> [!danger] One hand-rolled check survives
> `DashboardController.php:90` uses `auth()->user()?->role === 'owner'` — a string literal, not `isOwner()`. It only widens what an owner sees, so it is not a hole, but it is precisely the drift the rule exists to prevent. Noted in [[Path — Rendering the dashboard]].

## The three exclusion patterns

Worth recognising, because they encode real product decisions.

### `! isAdmin()` — kitchen access

```php
Gate::define('view-feedback',   fn (User $user) => ! $user->isAdmin());
Gate::define('view-stock-take', fn (User $user) => ! $user->isAdmin());
Gate::define('view-tally',      fn (User $user) => ! $user->isAdmin());
Gate::define('view-leave',      fn (User $user) => ! $user->isAdmin());
Gate::define('view-inventory',  fn (User $user) => ! $user->isAdmin());   // since 1.10.17
```

An admin is a support operator, not kitchen staff. They resolve tickets and reset passwords. Nothing about food.

### `! isAdmin() && ! isOwner()` — separation of duties

```php
Gate::define('record-tally',      fn (User $user) => ! $user->isAdmin() && ! $user->isOwner());
Gate::define('record-stock-take', fn (User $user) => ! $user->isAdmin() && ! $user->isOwner());
Gate::define('submit-feedback',   fn (User $user) => ! $user->isAdmin() && ! $user->isOwner());
Gate::define('submit-leave',      fn (User $user) => ! $user->isAdmin() && ! $user->isOwner());
```

**The Owner is deliberately locked out of recording.** Whoever counts must not be whoever reviews the count — that separation is the control. The Owner reviews [[Tally variance]], not produces it.

Same logic for feedback: the Owner oversees peer ratings but does not submit them. Feedback from the person who controls your job is not peer feedback. [[Feedback averages]].

And for leave: the Owner decides applications, so they cannot also submit them.

### `isOwner()` — trust and money

`view-audit-log`, `manage-users`, `manage-sections`, `manage-events`, `decide-leave`, `export-feedback-pdf`.

## Admin, and the escalation guard

Admins get exactly four things: `view-support-tickets`, `manage-support-tickets`, `view-users`, `manage-user-passwords`. They land on the tickets queue and have a two-item sidebar. `DashboardController` redirects them away from the dashboard entirely.

> [!danger] `manage-user-passwords` is gated *twice*
> The gate lets an admin reset passwords. A second check in `UserController::updatePassword` blocks them from resetting an **owner or admin** password.
>
> Without it, an admin resets the Owner's password, logs in as the Owner, and has the whole system. The gate cannot express "may reset *some* passwords", so the target check lives in the controller. This is the one place where authorization is legitimately not entirely in a gate — the rule depends on the *target*, not just the actor.

## The deleted Viewer role

`viewer` was scaffolded as a read-only role whose access was granted page by page, through a `user_permissions` row per allowed page:

```php
Gate::define('view-sales', fn (User $user) => $user->isManager() || $user->hasPermission('view-sales'));
```

It was never used. At deletion in 1.10.48 production held **zero viewer accounts and zero `user_permissions` rows**, so the `||` branch had never once evaluated true — five gates, a model, a relation, a controller sync block and a checkbox panel in the users modal, all reachable only by first assigning a role nobody assigned.

The `user_permissions` table and its migration are kept; dropping an empty table buys nothing and a migration that deletes data is worth more scrutiny than one that leaves it. `users.role` still defaults to `'viewer'` at the database level from the original 2026-04-29 migration — harmless, since every insert path sets the role explicitly and a stray `viewer` row would now fail every gate.

## Demo flag

`users.is_demo` is orthogonal to role — a demo user has a real role *and* the flag. It routes them to a separate database rather than changing what they may do. [[Demo sandbox]].

The one place it appears in a gate:

```php
Gate::define('toggle-maintenance', fn (User $user) => ($user->isOwner() || $user->isAdmin()) && ! $user->is_demo);
```

Because maintenance state lives in the live-pinned cache, a demo owner's toggle would 503 the real restaurant. Commit `fb627fc`.

## Landing pages

| Role | Lands on |
|---|---|
| Owner, Head Chef | `/dashboard` |
| Junior Chef | `/prep` — redirected |
| Admin | `/support-tickets` — redirected |

Both redirects are in `DashboardController@index`, **before** `Gate::authorize()`. See [[Path — Rendering the dashboard]].

## Accounts

Production, all `welcome1234` on first login:

| Name | Email | Role |
|---|---|---|
| Alex Tan | `alex@example.test` | owner |
| Sam | `sam@example.test` | head_chef |
| Morgan | `chef1@example.test` | junior_chef |
| Chris | `chef2@example.test` | junior_chef |
| Dani | `chef3@example.test` | junior_chef, `preferred_language = 'id'` |

Demo accounts and their isolation: [[Demo sandbox]].

## See also

[[Authorization gates]] · [[Gates matrix]] · [[Demo sandbox]] · [[Audit trail]]
