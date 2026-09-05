<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Cache::get('maintenance_mode', false)) {
            $user = $request->user();

            // Let owners and admins through always (admins manage maintenance + tickets)
            if ($user && in_array($user->role, ['owner', 'admin'], true)) {
                return $next($request);
            }

            // Let login/logout routes through so owner can authenticate.
            // Use path-based check (is()) as belt-and-suspenders alongside routeIs()
            // in case route resolution hasn't run yet at middleware execution time.
            if ($request->is('login', 'logout', 'invite/accept/*')
                || $request->routeIs('login', 'logout')) {
                return $next($request);
            }

            return response()->view('maintenance', [], 503);
        }

        return $next($request);
    }
}
