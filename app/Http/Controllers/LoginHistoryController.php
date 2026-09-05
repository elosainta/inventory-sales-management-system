<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use Illuminate\Support\Facades\Gate;

class LoginHistoryController extends Controller
{
    public function index()
    {
        Gate::authorize('view-audit-log');

        $histories = LoginHistory::with('user')
            ->orderByDesc('logged_in_at')
            ->paginate(50);

        return view('login-history.index', compact('histories'));
    }
}
