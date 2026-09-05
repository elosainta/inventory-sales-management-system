<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class MaintenanceModeController extends Controller
{
    public function toggle(): RedirectResponse
    {
        Gate::authorize('toggle-maintenance');

        $current = Cache::get('maintenance_mode', false);
        Cache::forever('maintenance_mode', ! $current);

        $status = ! $current ? 'enabled' : 'disabled';

        return back()->with('status', "Maintenance mode {$status}.");
    }
}
