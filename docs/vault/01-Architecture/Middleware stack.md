# Middleware stack

Three custom middleware, appended to the `web` group in `bootstrap/app.php`:

```php
$middleware->trustProxies(at: '*');                    // Cloudflare is the only way in
$middleware->encryptCookies(except: ['app_locale']); // readable before sign-in

$middleware->web(append: [
    MaintenanceMode::class,
    SetLocale::class,
    UseDemoDatabase::class,
]);
```

Appended, so they run **after** the framework's session and authentication middleware. That ordering is what makes all three possible at all — each needs `auth()->user()`.

`trustProxies(at: '*')` is safe only because the DO Cloud Firewall lets nothing but Cloudflare reach the origin ([[VPS and hosting#Origin lockdown]]); without it `$request->ip()` is a Cloudflare edge node, so login history records the wrong address and rate limiters lump strangers together.

> [!note] `HandleInertiaRequests` is gone
> It was the first of four until React and Inertia were removed in 1.10.37 ([[Architecture overview]]). If an older note or commit mentions it, that is why.

---

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
| 1 | `MaintenanceMode` | live cache, `auth()->user()` for the owner bypass |
| 2 | `SetLocale` | `auth()->user()->preferred_language`, else the `app_locale` cookie |
| 3 | `UseDemoDatabase` | `auth()->user()->is_demo`, resolved on the **live** connection |

## See also

[[Request lifecycle]] · [[Demo sandbox]] · [[Environment variables]] · [[Authorization gates]]
