<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Part timer: prep, stock-take, wastage and inventory. Nothing else.
 *
 * A new role is dangerous in this codebase because several gates are written
 * as `fn () => true` or `! $user->isAdmin()`. Those hand a brand-new role
 * access by default, silently — view-tally, view-production, view-leave and
 * view-feedback would all have opened to this one, including the two that
 * carry personal data. The whole matrix is pinned below rather than the happy
 * path, because the dangerous half is the half nobody thinks to check.
 *
 * "Can key in, cannot edit or delete" is enforced in three places and tested
 * in all three: the gate, the form request, and the controller. The disabled
 * fields in the modal are not one of them — a form is not a boundary.
 */
class PartTimerAccessTest extends TestCase
{
    use RefreshDatabase;

    private function partTimer(): User
    {
        return User::factory()->create(['role' => User::ROLE_PART_TIMER, 'is_demo' => false]);
    }

    /** No InventoryItem factory in this repo — tests build them by hand. */
    private function item(): InventoryItem
    {
        return InventoryItem::create([
            'name'              => 'Chicken Thigh',
            'category'          => 'Meat',
            'unit'              => 'kg',
            'quantity_on_hand'  => 4,
            'reorder_threshold' => 1,
            'unit_cost'         => 10,
        ]);
    }

    public static function allowedGates(): array
    {
        return [
            'prep checklist'  => ['view-checklist'],
            'prep overview'   => ['overview-checklist'],
            'stock-take list' => ['view-stock-take'],
            'stock-take entry'=> ['record-stock-take'],
            'wastage list'    => ['view-wastage'],
            'wastage entry'   => ['manage-wastage'],
            'inventory list'  => ['view-inventory'],
            'inventory keyin' => ['record-inventory'],
            'about'           => ['view-about'],
            'support'         => ['submit-support'],
        ];
    }

    /** The half that matters. */
    public static function deniedGates(): array
    {
        return [
            // Asked for explicitly.
            'leave'             => ['view-leave'],
            'leave submission'  => ['submit-leave'],
            // Personal data, same reasoning as leave.
            'peer feedback'     => ['view-feedback'],
            'feedback entry'    => ['submit-feedback'],
            // Not asked for, and would have leaked from `fn () => true`.
            'tally'             => ['view-tally'],
            'tally entry'       => ['record-tally'],
            // Not asked for, and would have leaked from `! isAdmin()`.
            'production'        => ['view-production'],
            'production entry'  => ['manage-production'],
            // Editing or deleting an inventory item, as opposed to keying one in.
            'inventory edit'    => ['manage-inventory'],
            // Money.
            'dashboard'         => ['view-dashboard'],
            'sales'             => ['view-sales'],
            'purchases'         => ['view-purchases'],
            'market purchases'  => ['view-market-purchases'],
            'suppliers'         => ['view-suppliers'],
            'recipes'           => ['view-recipes'],
            'petty cash'        => ['manage-float'],
            'invoice scan'      => ['use-invoice-scan'],
            'pdf export'        => ['export-pdf'],
            // Management.
            'users'             => ['manage-users'],
            'sections'          => ['manage-sections'],
            'events'            => ['manage-events'],
            'audit log'         => ['view-audit-log'],
            'daily report'      => ['view-daily-report'],
            'support queue'     => ['view-support-tickets'],
            'maintenance'       => ['toggle-maintenance'],
            'delete entries'    => ['delete-entries'],
            'global search'     => ['search-global'],
            // Own account settings, incl. changing their own password.
            'profile'           => ['edit-profile'],
        ];
    }

    #[DataProvider('allowedGates')]
    public function test_a_part_timer_holds_the_gate(string $gate): void
    {
        $this->assertTrue(Gate::forUser($this->partTimer())->allows($gate), "part timer should hold {$gate}");
    }

    #[DataProvider('deniedGates')]
    public function test_a_part_timer_does_not_hold_the_gate(string $gate): void
    {
        $this->assertFalse(Gate::forUser($this->partTimer())->allows($gate), "part timer must NOT hold {$gate}");
    }

    public function test_a_part_timer_cannot_reach_any_profile_route(): void
    {
        // Four routes on two controllers. Hiding the sidebar link closes none
        // of them, and password.update is on PasswordController, so gating
        // ProfileController alone would leave the password form posting fine.
        $partTimer = $this->partTimer();

        $this->actingAs($partTimer)->get(route('profile.edit'))->assertForbidden();
        $this->actingAs($partTimer)->patch(route('profile.update'), [
            'name'  => 'Valid Name',
            'email' => 'valid@example.test',
        ])->assertForbidden();
        $this->actingAs($partTimer)->delete(route('profile.destroy'), ['password' => 'x'])->assertForbidden();
        $this->actingAs($partTimer)->put(route('password.update'), [
            'current_password'      => 'password',
            'password'              => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertForbidden();
    }

    public function test_a_part_timer_can_key_a_count_in(): void
    {
        $item = $this->item();

        $this->actingAs($this->partTimer())
            ->patch(route('inventory.update', $item), ['quantity_on_hand' => 9])
            ->assertRedirect();

        $this->assertEquals(9, $item->fresh()->quantity_on_hand);
    }

    public function test_a_part_timer_cannot_rename_or_reprice_while_keying_a_count_in(): void
    {
        // The disabled fields in the modal stop an honest mistake. This is the
        // hand-rolled POST they do not stop — unit_cost feeds every plate cost.
        $item = $this->item();

        $this->actingAs($this->partTimer())
            ->patch(route('inventory.update', $item), [
                'quantity_on_hand' => 9,
                'name'             => 'Renamed',
                'unit_cost'        => 9999,
            ])
            ->assertRedirect();

        $fresh = $item->fresh();
        $this->assertSame('Chicken Thigh', $fresh->name);
        $this->assertEquals(10, $fresh->unit_cost);
        $this->assertEquals(9, $fresh->quantity_on_hand);
    }

    public function test_a_part_timer_cannot_delete_an_inventory_item(): void
    {
        $item = $this->item();

        $this->actingAs($this->partTimer())
            ->delete(route('inventory.destroy', $item))
            ->assertForbidden();

        $this->assertNotNull($item->fresh());
    }
}
