<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use Illuminate\Support\Facades\Route;

// `cache.headers:no_store` is load-bearing, not a tidy-up. Without it the
// browser restores /login from its cache on a Back press instead of asking
// the server, so a signed-in user pressing Back lands on a stale login form
// rather than being bounced onward. no-store forces a real request, which the
// `guest` middleware then redirects to the user's home page as intended.
Route::middleware(['guest', 'cache.headers:no_store'])->group(function () {
    // Registration is invitation-only — see InvitationController.
    // Public /register routes intentionally removed.

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Password reset by email was removed — there is no public "forgot password"
    // flow. Owners/admins reset other users' passwords from the Users page.
});

Route::middleware('auth')->group(function () {
    // Email verification and confirm-password were removed too. User does not
    // implement MustVerifyEmail, so `verified` passed unconditionally and the
    // whole verify flow was unreachable; nothing ever applied `password.confirm`.

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
