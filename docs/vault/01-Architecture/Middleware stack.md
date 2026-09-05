# Middleware stack

Four custom middleware, appended to the `web` group in `bootstrap/app.php:14-21`:

```php
$middleware->web(append: [
    \App\Http\Middleware\HandleInertiaRequests::class,
    \App\Http\Middleware\MaintenanceMode::class,
    \App\Http\Middleware\SetLocale::class,
    \App\Http\Middleware\UseDemoDatabase::class,
]);
```

Appended, so they run **after** the framework's session and authentication middleware. That ordering is what makes the last two possible at all — both need `auth()->user()`.

---

## `HandleInertiaRequests`

Vestigial. Inertia is installed and shares props, but only the login page is a React component. See the warning in [[Architecture overview]]. Harmless; not worth removing while the login page still uses it.

## `MaintenanceMode`

Reads `Cache::get('maintenance_mode')` and returns `resources/views/maintenance.blade.php` with HTTP 503 to everyone except owners and the login routes.

**Why the owner bypass:** without it, whoever flipped the switch locks themselves out and the only way back is `docker compose exec app php artisan tinker`.

**Why database cache, not Laravel's built-in `php artisan down`:** the built-in writes a file into `storage/`, which is a container volume. The toggle needs to be a UI button the Owner can press, and the state needs to survive a container rebuild. Database cache gives both.

Gate: `toggle-maintenance`, owner or admin **and not demo**:

```php
Gate::define('toggle-maintenance',
    fn (User $user) => ($user->isOwner() || $user->isAdmin()) && ! $user->is_demo);
```

> [!danger] Why demo owners are excluded — commit `fb627fc`
> The cache is pinned to the **live** connection (see [[Demo sandbox]]). A demo owner pressing the toggle would write `maintenance_mode = true` to the live cache and 503 the real restaurant from inside a training sandbox.

## `SetLocale`

Calls `App::setLocale()` from `users.preferred_language`. Translations in `lang/id.json`.

Coverage is narrow and honest about it: only `resources/views/prep/index.blade.php` is translated. All PDFs are English-only, which is acceptable because no PDF route is reachable by a junior chef. Dani (`chef3@example.test`) runs with `ms`.

## `UseDemoDatabase`

The reason the whole demo sandbox is safe. Full explanation: [[Demo sandbox]].

```php
// resolve the user on the LIVE connection first
if ($user->is_demo) {
    DB::setDefaultConnection('mariadb_demo');
}
```

> [!danger] This must stay last in the stack
> It reads `auth()->user()`, so it must run after authentication. And it changes the *default* connection for everything downstream, so anything that must not be redirected into the sandbox — session, cache, queue — has to be pinned explicitly in `.env` rather than relying on position. See [[Environment variables]].

---

## Ordering summary

| Position | Middleware | Needs |
|---|---|---|
| 1 | `HandleInertiaRequests` | nothing |
| 2 | `MaintenanceMode` | live cache, `auth()->user()` for the owner bypass |
| 3 | `SetLocale` | `auth()->user()->preferred_language` |
| 4 | `UseDemoDatabase` | `auth()->user()->is_demo`, resolved on the **live** connection |

## See also

[[Request lifecycle]] · [[Demo sandbox]] · [[Environment variables]] · [[Authorization gates]]
