# Authorization gates

**51 gates. One mechanism. No policies, no middleware role checks, no `if ($user->role === …)` scattered through controllers.**

Live matrix of who passes what: [[Gates matrix]] — generated, always current.

## Where they live

All of them in one place: `app/Providers/AppServiceProvider::boot()`, `app/Providers/AppServiceProvider.php:37-140`.

```php
Gate::define('manage-sales', fn (User $user) => $user->isManager());
```

One file, one screenful, greppable. Authorization is a thing you can *read* rather than a thing you have to hunt for. Given a five-person kitchen with no per-record ownership rules, Laravel Policies would add a class per model for no gain.

## The two rules

### 1 · `Gate::authorize()` is the first line of every controller method

Including `index`, `show`, and `exportPdf`. From `CLAUDE.md`: *"no exceptions."*

```php
public function index()
{
    Gate::authorize('view-sales');
    // …
}
```

Failure throws `AuthorizationException` → **403**. Not a redirect, not a silent empty list.

The rule is mechanical on purpose. Judgement about which methods "need" a check is how `exportPdf` ends up unguarded and a junior chef downloads the financial PDF. Verify:

```bash
grep -L "Gate::authorize" app/Http/Controllers/*.php
```

**The one deviation** is `DashboardController@index`, which redirects junior chefs and admins to their own landing pages before authorizing. The gate still runs before any data is read. [[Path — Rendering the dashboard]].

### 2 · Views use `@can`, and the gate is the same one

```blade
@can('dismiss-low-stock')
    <button>Dismiss</button>
@endcan
```

`@can` controls **rendering**; `Gate::authorize()` controls **access**. Both consult the same definition, so the button a user can see is exactly the button they can use — and hiding a button is never mistaken for securing an endpoint.

## Naming

| Prefix | Meaning | Typical rule |
|---|---|---|
| `view-*` | read a list or record | `isManager()`, or `! isAdmin()` where the whole kitchen reads |
| `manage-*` | create, edit, delete | `isManager()` or `isOwner()` |
| `record-*` | enter a count | `! isAdmin() && ! isOwner()` |
| `submit-*` | staff-initiated | `! isAdmin() && ! isOwner()` |
| `export-*` | produce a PDF | `isManager()` or `isOwner()` |
| `toggle-*`, `decide-*`, `dismiss-*` | one specific action | varies |

## The four shapes

### `isManager()` — the workhorse

```php
Gate::define('manage-sales', fn (User $user) => $user->isManager());
```

Most operational gates. Owner + Head Chef.

### The commercial read gates

```php
Gate::define('view-sales', fn (User $user) => $user->isManager());
```

Five `view-*` gates: `view-suppliers`, `view-purchases`, `view-wastage`, `view-sales`, `view-recipes`.

These carried `|| $user->hasPermission('view-sales')` until 1.10.48 — a per-account grant belonging to the `viewer` role. That role never held an account and `user_permissions` never held a row, so the branch could not evaluate true for anyone; it was deleted along with the role. `view-inventory` had already left this shape in 1.10.17 to become `! isAdmin()`, because the whole kitchen reads inventory. [[Roles]].

### Exclusion — the interesting ones

```php
Gate::define('record-tally', fn (User $user) => ! $user->isAdmin() && ! $user->isOwner());
```

**The Owner is excluded on purpose.** Whoever counts is not whoever reviews the count. Full reasoning in [[Roles]].

### Always true

```php
Gate::define('submit-support', fn (User $user) => true);
Gate::define('view-about',     fn (User $user) => true);
```

Defined rather than left ungated, so the intent is explicit and the matrix is complete. `Gate::authorize('view-about')` still requires an authenticated user — the gate receives a `User`.

## What gates cannot express

Two real cases where authorization depends on the **target**, not just the actor:

### Password resets

`manage-user-passwords` lets an admin reset passwords. A second check inside `UserController::updatePassword` blocks resetting an **owner or admin** password.

> [!danger] Without that check, an admin resets the Owner's password and owns the system
> The gate cannot see the target user, so the rule lives in the controller. It is the one legitimate place authorization is not entirely in a gate — and it needs a comment saying so wherever it appears.

### Attachment downloads

Feedback attachments are restricted to sender, recipient or Owner — again a target-dependent rule, enforced in `FeedbackController`.

Pattern: **actor-only rules go in gates; actor-plus-target rules go in the controller, next to the target.**

## Global search is gate-aware

`SearchController` only queries buckets the user can view. Managers get Inventory, Suppliers, Recipes, Purchases, Sales, Wastage, Events; Owner and Admin additionally get Support Tickets and Users, checked against `view-support-tickets` / `view-users`.

The placeholder text is role-aware too — admins see *"Search support tickets, users…"*.

This is the right pattern: a search box that queries everything and filters results afterwards leaks existence through result counts and timing.

Gate: `search-global` → everyone except junior chefs.

## Adding a gate

1. Define it in `AppServiceProvider::boot()` next to its family.
2. `Gate::authorize('…')` as the first line of every controller method.
3. `@can('…')` around any UI that triggers it.
4. Add it to the nav arrays in `layouts/app-shell.blade.php` if it owns a page. The Owner has a **separate hand-written list** further down the same file; miss it and the Owner loses the link.
5. Add its bucket to `SearchController` if the module is searchable — global search only queries what the user can view.
6. Commit — the pre-commit hook regenerates [[Gates matrix]] and you can read the new row.

**Changing** a gate is the same list in reverse: grep the gate name and check all five sites. A gate is never one edit, and the failure mode is an interface that lies — a nav link to a 403, or a checkbox that decides nothing.

## Verifying

The matrix is generated by evaluating each gate against a synthetic user per role (`app/Console/Commands/VaultSync.php`), so it reflects the code rather than someone's memory of it. Read [[Gates matrix]] after any change — an unexpected ✅ is a bug you can see.

The matrix proves the **logic**. It does not prove that the seven live accounts land where you meant, because roles are assigned per person. After an access change, evaluate the gate against the real users too:

```php
foreach (App\Models\User::orderBy('id')->get() as $u) {
    echo $u->name . ' ' . (Gate::forUser($u)->allows('view-inventory') ? 'VIEW' : '-') . PHP_EOL;
}
```

## See also

[[Roles]] · [[Gates matrix]] · [[Audit trail]] · [[Demo sandbox]]
