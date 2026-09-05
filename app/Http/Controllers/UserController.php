<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserLanguageRequest;
use App\Http\Requests\UpdateUserPasswordRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index()
    {
        Gate::authorize('view-users');

        // The hidden demo account is only visible to Admins.
        $users = User::when(! auth()->user()->isAdmin(), fn ($q) => $q->where('is_demo', false))
            ->orderBy('role')->orderBy('name')->get();

        return view('users.index', compact('users'));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        Gate::authorize('manage-users');

        $data = $request->validated();

        // Same escalation guard as updatePassword, updateLanguage and destroy,
        // plus the half those three do not need: an Admin may not GRANT the
        // owner or admin role either. Without it the ladder is three clicks —
        // reset a junior chef's password (which an Admin may do), promote that
        // chef to Owner, sign in as them — and the one thing the Owner held
        // back from Admin, the financial dashboard, is gone.
        if (! auth()->user()->isOwner()
            && (in_array($user->role, [User::ROLE_OWNER, User::ROLE_ADMIN], true)
                || in_array($data['role'] ?? $user->role, [User::ROLE_OWNER, User::ROLE_ADMIN], true))) {
            abort(403);
        }

        if ($user->isOwner() && $data['role'] !== User::ROLE_OWNER) {
            if (User::where('role', User::ROLE_OWNER)->count() <= 1) {
                return back()->with('error', 'Cannot change role. This is the last Owner account.');
            }
        }

        $user->update($data);

        return back()->with('success', 'User updated.');
    }

    public function updatePassword(UpdateUserPasswordRequest $request, User $user)
    {
        Gate::authorize('manage-user-passwords');

        // Privilege-escalation guard: a non-owner (Admin) may not reset the
        // password of an Owner or another Admin.
        if (! auth()->user()->isOwner()
            && in_array($user->role, [User::ROLE_OWNER, User::ROLE_ADMIN], true)) {
            abort(403);
        }

        $user->update(['password' => $request->validated('password')]);

        return back()->with('success', 'Password updated.');
    }

    public function updateLanguage(UpdateUserLanguageRequest $request, User $user)
    {
        Gate::authorize('manage-user-language');

        // Same escalation guard as updatePassword and destroy: a non-owner
        // (Admin) may not change the display language of an Owner or another
        // Admin. Enforced here, not merely hidden in the view.
        if (! auth()->user()->isOwner()
            && in_array($user->role, [User::ROLE_OWNER, User::ROLE_ADMIN], true)) {
            abort(403);
        }

        $user->update(['preferred_language' => $request->validated('preferred_language')]);

        $language = $user->preferred_language === 'id' ? 'Bahasa Indonesia' : 'English';

        return back()->with('success', "Display language for {$user->name} set to {$language}.");
    }

    public function destroy(User $user)
    {
        Gate::authorize('delete-users');

        // Privilege-escalation guard, mirroring updatePassword: a non-owner
        // (Admin) may not delete an Owner or another Admin.
        if (! auth()->user()->isOwner()
            && in_array($user->role, [User::ROLE_OWNER, User::ROLE_ADMIN], true)) {
            abort(403);
        }

        if ($user->isOwner() && User::where('role', User::ROLE_OWNER)->count() <= 1) {
            return back()->with('error', 'Cannot delete the last Owner account.');
        }

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account here. Use Profile instead.');
        }

        $user->delete();

        return back()->with('success', 'User removed.');
    }
}
