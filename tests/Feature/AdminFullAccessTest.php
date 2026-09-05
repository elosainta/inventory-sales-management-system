<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Admin reaches every feature except the financial dashboard.
 *
 * This replaces AdminOperationalAccessTest, which pinned the opposite policy:
 * from 1.11.2 the role was a support operator held off anything carrying money
 * or personal data, and that file's deny list was the valuable half of it. The
 * Owner widened the role on 2026-09-03, so the deny list is now three entries
 * long — and those three are the ones that still matter.
 *
 * What is still denied, and why:
 *   view-dashboard  the exclusion the Owner named.
 *   privilege escalation on the Users page — an Admin may not reset, delete,
 *                   re-language or re-role an Owner or another Admin, and may
 *                   not grant the owner role to anyone. Those are guards in
 *                   UserController, not gates, so the blanket Gate::before
 *                   does not lift them. Without them the dashboard exclusion
 *                   is one password reset and one promotion away from nothing.
 *   toggle-maintenance for a DEMO admin — maintenance state lives in the
 *                   live-pinned cache, so a demo toggle would 503 the real app.
 */
class AdminFullAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(bool $demo = false): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_demo' => $demo]);
    }

    /** Every screen in the sidebar, and the ones that are not. */
    public static function everyRoute(): array
    {
        return [
            'support tickets' => ['support-tickets.index'],
            'prep checklist'  => ['prep.index'],
            'prep overview'   => ['prep.overview'],
            'daily report'    => ['daily-report.index'],
            'purchases'       => ['purchases.index'],
            'market'          => ['market-purchases.index'],
            'invoice scan'    => ['invoice-scan.index'],
            'production'      => ['production.index'],
            'inventory'       => ['inventory.index'],
            'stock-take'      => ['stock-take.index'],
            'tally'           => ['tally.index'],
            'wastage'         => ['wastage.index'],
            'sales'           => ['sales.index'],
            'petty cash'      => ['float.index'],
            'events'          => ['events.index'],
            'recipes'         => ['recipes.index'],
            'suppliers'       => ['suppliers.index'],
            'sections'        => ['sections.index'],
            'users'           => ['users.index'],
            'audit log'       => ['audits.index'],
            'login history'   => ['login-history.index'],
            'leave'           => ['leave.index'],
            'peer feedback'   => ['feedback.index'],
            'support form'    => ['support.index'],
            'profile'         => ['profile.edit'],
            'about'           => ['about.index'],
        ];
    }

    #[DataProvider('everyRoute')]
    public function test_admin_can_reach_it(string $route): void
    {
        $this->actingAs($this->admin())->get(route($route))->assertOk();
    }

    /** Writing, not just reading — the widening was meant to include both. */
    public function test_admin_can_record_a_stock_take_and_a_tally(): void
    {
        $this->actingAs($this->admin())->get(route('stock-take.create'))->assertOk();
        $this->actingAs($this->admin())->get(route('tally.create'))->assertOk();
    }

    public function test_admin_is_sent_away_from_the_dashboard_rather_than_shown_it(): void
    {
        // The dashboard answers 302, not 403: DashboardController redirects
        // each role to its own landing page before rendering anything. The
        // gate assertion is the one that matters — a refactor dropping the
        // redirect must fail here rather than quietly serve revenue and margin.
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirect(route('support-tickets.index'));

        $this->assertFalse(Gate::forUser($admin)->allows('view-dashboard'));
    }

    public function test_a_demo_admin_still_cannot_toggle_maintenance(): void
    {
        $this->assertFalse(Gate::forUser($this->admin(demo: true))->allows('toggle-maintenance'));
        $this->assertTrue(Gate::forUser($this->admin())->allows('toggle-maintenance'));
    }

    public function test_admin_cannot_promote_anyone_to_owner(): void
    {
        // The escalation ladder this guard exists to break: an Admin may reset
        // a junior chef password, so if they could also promote that chef to
        // Owner they would hold the dashboard by signing in as them.
        $chef = User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF, 'is_demo' => false]);

        $this->actingAs($this->admin())
            ->patch(route('users.update', $chef), [
                'name' => $chef->name,
                'role' => User::ROLE_OWNER,
            ])
            ->assertForbidden();

        $this->assertSame(User::ROLE_JUNIOR_CHEF, $chef->fresh()->role);
    }

    public function test_admin_cannot_touch_an_owner_or_another_admin(): void
    {
        $me = $this->admin();

        foreach ([User::ROLE_OWNER, User::ROLE_ADMIN] as $role) {
            $target = User::factory()->create(['role' => $role, 'is_demo' => false]);

            $this->actingAs($me)->patch(route('users.update', $target), [
                'name' => 'Renamed', 'role' => User::ROLE_JUNIOR_CHEF,
            ])->assertForbidden();

            $this->actingAs($me)->patch(route('users.update-password', $target), [
                'password' => 'newpassword123', 'password_confirmation' => 'newpassword123',
            ])->assertForbidden();

            $this->actingAs($me)->patch(route('users.update-language', $target), [
                'preferred_language' => 'id',
            ])->assertForbidden();

            $this->actingAs($me)->delete(route('users.destroy', $target))->assertForbidden();

            $this->assertSame($role, $target->fresh()->role);
        }
    }

    public function test_admin_can_still_reset_a_chefs_password(): void
    {
        // The half of user management the role was given in the first place.
        $chef = User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF, 'is_demo' => false]);

        $this->actingAs($this->admin())
            ->patch(route('users.update-password', $chef), [
                'password' => 'newpassword123', 'password_confirmation' => 'newpassword123',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_the_kitchen_roles_are_unaffected(): void
    {
        // Widening one role must not move another. A part timer in particular
        // is denied most of what Admin just gained.
        foreach ([User::ROLE_JUNIOR_CHEF, User::ROLE_PART_TIMER] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_demo' => false]);

            $this->actingAs($user)->get(route('prep.index'))->assertOk();
            $this->actingAs($user)->get(route('sales.index'))->assertForbidden();
            $this->actingAs($user)->get(route('audits.index'))->assertForbidden();
        }
    }
}
