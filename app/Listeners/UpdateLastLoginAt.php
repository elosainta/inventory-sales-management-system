<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use Illuminate\Auth\Events\Login;

class UpdateLastLoginAt
{
    public function handle(Login $event): void
    {
        $event->user->update(['last_login_at' => now()]);

        LoginHistory::create([
            'user_id'      => $event->user->id,
            'ip_address'   => request()->ip(),
            'logged_in_at' => now(),
        ]);
    }
}
