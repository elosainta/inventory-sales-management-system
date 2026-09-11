<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        // Login lands each role on a page it can actually open, so the
        // destination depends on the role — a junior chef goes to /prep, an
        // admin to /support-tickets. Assert the manager case the route names.
        $user = User::factory()->create(['role' => User::ROLE_OWNER]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_a_distributed_attack_is_locked_out_by_the_email_counter(): void
    {
        $user = User::factory()->create();

        // Twenty wrong guesses, every one from a different address. The
        // email+IP counter never reaches five on any of them, so only the
        // per-email counter can stop this — which is the whole point of it.
        foreach (range(1, 20) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.$i])
                ->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        // Even the RIGHT password, from an address that has never been seen,
        // is now refused. Before the email counter existed this logged in.
        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }
}
