<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A page you may open must be a page you can find.
 *
 * The Owner had a second, hand-written nav list, and it had drifted nine links
 * behind their gates: Inventory, Sales, Purchases, Market, Production,
 * Wastage, Suppliers, Events and Login History were all authorised and none of
 * them were in the sidebar. Nothing was broken in a way anyone could see — the
 * routes answered fine if you knew the URL — so it survived until someone went
 * looking for Inventory.
 *
 * Both halves are asserted, because both are ways the sidebar can lie:
 * a missing link hides a page, and a surplus link leads to a 403.
 */
class SidebarMatchesGatesTest extends TestCase
{
    use RefreshDatabase;

    /** Route => the gate that guards it, for the pages that live in the sidebar. */
    private const SIDEBAR = [
        'support-tickets.index'  => 'view-support-tickets',
        'prep.index'             => 'view-checklist',
        'prep.overview'          => 'overview-checklist',
        'daily-report.index'     => 'view-daily-report',
        'purchases.index'        => 'view-purchases',
        'market-purchases.index' => 'view-market-purchases',
        'invoice-scan.index'     => 'use-invoice-scan',
        'production.index'       => 'view-production',
        'inventory.index'        => 'view-inventory',
        'stock-take.index'       => 'view-stock-take',
        'tally.index'            => 'view-tally',
        'rnd.index'              => 'view-rnd',
        'staff-meals.index'      => 'view-staff-meal',
        'wastage.index'          => 'view-wastage',
        'sales.index'            => 'view-sales',
        'dashboard'              => 'view-dashboard',
        'float.index'            => 'manage-float',
        'events.index'           => 'manage-events',
        'recipes.index'          => 'view-recipes',
        'suppliers.index'        => 'view-suppliers',
        'sections.index'         => 'manage-sections',
        'users.index'            => 'view-users',
        'audits.index'           => 'view-audit-log',
        // The footer block. Left out of this list until 1.11.6, and that gap is
        // exactly what it failed to catch: Leave and Feedback were hand-written
        // with no @can, so a part timer saw two links that 403. A route missing
        // from this list is not checked in either direction — when a link is
        // added to the shell, it belongs here too.
        'leave.index'            => 'view-leave',
        'feedback.index'         => 'view-feedback',
        'support.index'          => 'submit-support',
        'profile.edit'           => 'edit-profile',
        'about.index'            => 'view-about',
    ];

    /**
     * Routes the "no surplus link" half must skip.
     *
     * The sidebar logo links to route('dashboard'), which is a landing route,
     * not the gated Dashboard page — it redirects juniors to prep and admins
     * to support tickets before Gate::authorize runs. So a junior chef's page
     * legitimately carries that href while failing view-dashboard, and the
     * assertion below cannot tell the logo from the nav item by URL alone.
     */
    private const NOT_A_NAV_LINK = ['dashboard'];

    public static function everyRole(): array
    {
        return [
            'owner'       => [User::ROLE_OWNER],
            'head chef'   => [User::ROLE_HEAD_CHEF],
            'junior chef' => [User::ROLE_JUNIOR_CHEF],
            'part timer'  => [User::ROLE_PART_TIMER],
            // Admin was exempt from this file until 2026-09-03, because its
            // sidebar was a hand-written list deliberately narrower than its
            // gates. That list is gone and the role now renders the same
            // gate-filtered nav as everyone else, so there is nothing left to
            // exempt — which is the point.
            'admin'       => [User::ROLE_ADMIN],
        ];
    }

    #[DataProvider('everyRole')]
    public function test_the_sidebar_links_everywhere_the_role_may_go(string $role): void
    {
        $user = User::factory()->create(['role' => $role, 'is_demo' => false]);

        $html = $this->actingAs($user)->get(route('prep.index'))->assertOk()->getContent();

        foreach (self::SIDEBAR as $route => $gate) {
            if (! Route::has($route) || ! Gate::forUser($user)->allows($gate)) {
                continue;
            }

            $this->assertStringContainsString(
                'href="' . route($route) . '"',
                $html,
                "{$role} passes {$gate} but has no sidebar link to {$route}"
            );
        }
    }

    #[DataProvider('everyRole')]
    public function test_the_sidebar_links_nowhere_the_role_may_not_go(string $role): void
    {
        $user = User::factory()->create(['role' => $role, 'is_demo' => false]);

        $html = $this->actingAs($user)->get(route('prep.index'))->assertOk()->getContent();

        foreach (self::SIDEBAR as $route => $gate) {
            if (in_array($route, self::NOT_A_NAV_LINK, true)) {
                continue;
            }

            if (! Route::has($route) || Gate::forUser($user)->allows($gate)) {
                continue;
            }

            $this->assertStringNotContainsString(
                'href="' . route($route) . '"',
                $html,
                "{$role} is denied {$gate} but the sidebar links to {$route} — that link is a 403"
            );
        }
    }

    public function test_the_owner_can_find_inventory(): void
    {
        // The reported bug, named outright so a regression says so.
        $owner = User::factory()->create(['role' => User::ROLE_OWNER, 'is_demo' => false]);

        $this->actingAs($owner)
            ->get(route('prep.index'))
            ->assertOk()
            ->assertSee('href="' . route('inventory.index') . '"', false);
    }

    public function test_admin_can_find_inventory(): void
    {
        // The deliberate hole in the money boundary — see view-inventory.
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_demo' => false]);

        $this->actingAs($admin)
            ->get(route('support-tickets.index'))
            ->assertOk()
            ->assertSee('href="' . route('inventory.index') . '"', false);
    }
}
