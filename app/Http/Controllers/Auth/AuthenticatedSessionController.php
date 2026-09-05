<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        // Land on a page this role can actually open. Sending everyone but a
        // junior chef to the dashboard bounced Admins straight off it, and
        // honouring intended() replayed whatever URL was saved while nobody
        // was signed in — which is how logging in as a chef could dump you on
        // a manager-only page and 403 you before you had done anything. The
        // cost is that a session that times out mid-work no longer returns you
        // to the exact page; you land on your home instead.
        return redirect(route($user->homeRoute(), absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
