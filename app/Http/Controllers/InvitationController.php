<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvitationRequest;
use App\Models\User;
use App\Notifications\UserInvited;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function store(StoreInvitationRequest $request)
    {
        Gate::authorize('manage-users');

        $data = $request->validated();

        $tempPassword = Str::random(10);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $tempPassword,
            'role'     => $data['role'],
        ]);

        try {
            Notification::route('mail', $data['email'])
                ->notify(new UserInvited($data['name'], $data['email'], $tempPassword, $data['role']));
        } catch (\Throwable $e) {
            Log::error('Welcome email failed for ' . $data['email'] . ': ' . $e->getMessage());
        }

        return back()->with('success', "Account created for {$data['name']}. Login credentials have been sent to {$data['email']}.");
    }
}
