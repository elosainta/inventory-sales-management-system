# Request lifecycle

What happens between a click and a rendered page. Traced through a real request: Head Chef submits the Log Sale form.

## The hops

### 1. `public/index.php` → `bootstrap/app.php`

Laravel 13 has no `Kernel.php`. The whole application configuration is one fluent call in `bootstrap/app.php:7-23`: routing files, the middleware stack, the exception handler.

### 2. Routing — `routes/web.php`

199 lines, all wrapped in `->middleware('auth')`. The route resolves to a controller method. Full generated table: [[Routes]].

> [!note] Route order matters in three places
> `create` and catalog routes are declared **before** `{wildcard}` routes, otherwise `/tally/create` binds `create` as an `{inventoryTally}` id and 404s. Applies to tally, stock-take and the stock-take item catalog.

### 3. Middleware

The web group, then four custom ones appended in `bootstrap/app.php:15-20`:

`HandleInertiaRequests` → `MaintenanceMode` → `SetLocale` → `UseDemoDatabase`

That order is load-bearing. [[Middleware stack]] explains why `UseDemoDatabase` must be last.

### 4. Form Request — `app/Http/Requests/StoreSaleRequest.php`

Laravel resolves the type-hinted Form Request out of the container and runs `rules()` **before the controller method body executes**. Validation failure redirects back with errors; the controller never runs.

### 5. Controller — `Gate::authorize()` first

```php
Gate::authorize('manage-sales');
```

Throws `AuthorizationException` → 403. Not a redirect, not a silent skip. See [[Authorization gates]].

### 6. Domain Action

`LogSale::execute()` computes derived values, opens a transaction, writes. [[Path — Logging a sale]] follows this hop in full.

### 7. Model events → audit

Any model using `LogsActivity` fires `created` / `updated` / `deleted` hooks that write an `audits` row inside the same transaction. Nothing calls the audit explicitly. [[Audit trail]].

### 8. Redirect → GET → Blade

Post/Redirect/Get. The view renders with `@money()` for currency and `@can()` for conditional UI.

```
POST /sales
  ├─ middleware
  ├─ StoreSaleRequest::rules()
  ├─ SaleController@store
  │    ├─ Gate::authorize('manage-sales')
  │    └─ LogSale::execute()
  │         └─ DB::transaction
  │              ├─ Sale::create()      → audits row
  │              └─ inventory deduction → audits rows
  └─ redirect()->back()
       └─ GET /sales → sales/index.blade.php
```

## Where things go wrong

| Symptom | Look at |
|---|---|
| 403 on a page that should work | [[Gates matrix]], then the gate closure in `AppServiceProvider` |
| 404 on `/x/create` | route declared after the `{x}` wildcard — see the note above |
| 503 for everyone but the Owner | maintenance mode is on — [[Middleware stack]] |
| Demo user's data appearing in live | `UseDemoDatabase` did not run — [[Demo sandbox]] |
| Blank/unstyled page after back button | fixed in `8d1f391`; cache headers on auth routes |

## See also

[[Architecture overview]] · [[Layering rules]] · [[Code paths index]]
