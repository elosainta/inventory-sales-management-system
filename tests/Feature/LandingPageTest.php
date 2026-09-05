<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Nobody should be able to sign in and land on a refusal page.
 *
 * Three places decide where an account goes, and they have to agree:
 *   1. AuthenticatedSessionController — where login sends you.
 *   2. `/` — where the bare domain sends you.
 *   3. DashboardController@index — which bounces the roles with no dashboard.
 *
 * (3) knew about junior chefs and admins and nothing else, so every role added
 * since inherited "go to the dashboard and get a 403". A part timer signing in
 * hit exactly that, three times, on 2026-09-03.
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public static function everyRole(): array
    {
        return [
            'owner'       => [User::ROLE_OWNER],
            'head chef'   => [User::ROLE_HEAD_CHEF],
            'junior chef' => [User::ROLE_JUNIOR_CHEF],
            'part timer'  => [User::ROLE_PART_TIMER],
            'admin'       => [User::ROLE_ADMIN],
        ];
    }

    #[DataProvider('everyRole')]
    public function test_the_bare_domain_never_lands_on_a_refusal(string $role): void
    {
        $user = User::factory()->create(['role' => $role, 'is_demo' => false]);

        $this->actingAs($user)->get('/')->assertStatus(302);

        // Follow it the whole way — a redirect into a 403 is still a 403.
        $this->actingAs($user)->followingRedirects()->get('/')->assertOk();
    }

    #[DataProvider('everyRole')]
    public function test_the_dashboard_route_never_refuses_anyone(string $role): void
    {
        // /dashboard is the landing route, not just the financial page: the
        // sidebar logo points at it from every page, for every role.
        $user = User::factory()->create(['role' => $role, 'is_demo' => false]);

        $this->actingAs($user)->followingRedirects()->get(route('dashboard'))->assertOk();
    }

    #[DataProvider('everyRole')]
    public function test_signing_in_lands_somewhere_the_role_can_open(string $role): void
    {
        $user = User::factory()->create([
            'role'     => $role,
            'is_demo'  => false,
            'password' => 'welcome1234',
        ]);

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'welcome1234',
        ]);

        $response->assertRedirect();

        $this->actingAs($user)->followingRedirects()->get($response->headers->get('Location'))->assertOk();
    }
}
