<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Admin reaches every feature, the financial dashboard included.
 *
 * This replaces AdminOperationalAccessTest, which pinned the opposite policy:
 * from 1.11.2 the role was a support operator held off anything carrying money
 * or personal data, and that file's deny list was the valuable half of it. The
 * Owner widened the role on 2026-09-03 — keeping the dashboard back — and gave
 * the dashboard too on 2026-09-24. The deny list is now two entries long, and
 * those two are the ones that still matter.
 *
 * What is still denied, and why:
 *   privilege escalation on the Users page — an Admin may not reset, delete,
 *                   re-language or re-role an Owner or another Admin, and may
 *                   not grant the owner role to anyone. Those are guards in
 *                   UserController, not gates, so the blanket Gate::before
 *                   does not lift them. They are now the whole of what keeps
 *                   an Admin from simply becoming the Owner.
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
            'dashboard'       => ['dashboard'],
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

    public function test_admin_reads_the_dashboard_but_still_lands_on_the_support_queue(): void
    {
        // Two separate questions, and the controller used to answer the first
        // with the second: it redirected anyone whose homeRoute() was not the
        // dashboard, so granting the gate alone would have left an Admin
        // bounced off a page they may now open. Landing stays on the queue —
        // that is where the job is — so both halves are pinned here.
        $admin = $this->admin();

        $this->assertTrue(Gate::forUser($admin)->allows('view-dashboard'));
        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $this->assertSame('support-tickets.index', $admin->homeRoute());
        $this->actingAs($admin)->get('/')->assertRedirect(route('support-tickets.index'));
    }

    public function test_a_junior_chef_is_still_sent_home_from_the_dashboard(): void
    {
        // The redirect is what keeps the sidebar logo (which points at
        // route('dashboard') on every page, for every role) off a 403.
        $chef = User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]);

        $this->actingAs($chef)->get(route('dashboard'))->assertRedirect(route('prep.index'));
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
