<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseDemoDatabase
{
    /**
     * Demo/training accounts live in the live `users` table (so they can sign in
     * normally), but every other query they make is redirected to a physically
     * separate sandbox database — an isolated clone of the live data. This lets
     * demo users freely create, edit and delete records and see each other's
     * changes, while it stays impossible for anything they do to touch real data.
     *
     * The user is resolved from the live connection first (the default is still
     * `mariadb` at this point), so authentication and the session — which is
     * pinned to the live connection via SESSION_CONNECTION — are never affected.
     * Only after we know the caller is a demo account do we swap the default
     * connection, so all subsequent Eloquent queries hit the demo database.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_demo) {
            DB::setDefaultConnection('mariadb_demo');
        }

        return $next($request);
    }
}
