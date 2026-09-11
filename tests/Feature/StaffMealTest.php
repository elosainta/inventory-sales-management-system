<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\StaffMeal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Staff meals, recorded as costing sheets.
 *
 * Three rules carry the weight:
 *
 *   Recording one DEDUCTS its lines from live stock — the team has eaten it.
 *   Quantities are summed per item first, because a sheet legitimately lists
 *   the same ingredient twice.
 *
 *   Editing does NOT deduct again, and deleting does not put anything back.
 *   The same rule as Sales, Wastage, Production and R&D, and for the same
 *   reason: a second deduction on every correction would make one typo cost
 *   real stock twice.
 *
 *   Every line's name and unit are snapshotted off the picked inventory item,
 *   never off the form, so a sheet cannot read as one ingredient while having
 *   deducted another.
 */
class StaffMealTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_demo' => false]);
    }

    private function item(string $name = 'Beras wangi', float $onHand = 10, float $cost = 4.20): InventoryItem
    {
        return InventoryItem::create([
            'name'             => $name,
            'category'         => 'Pantry',
            'unit'             => 'kg',
            'quantity_on_hand' => $onHand,
            'unit_cost'        => $cost,
        ]);
    }

    /** A recorded sheet, written straight to the database. */
    private function meal(array $overrides = [], ?array $lines = null): StaffMeal
    {
        $meal = StaffMeal::create(array_merge([
            'meal_date'    => now()->toDateString(),
            'dish'         => 'Nasi goreng kampung',
            'misc_percent' => 30,
            'remark'       => null,
            'created_by'   => $this->user(User::ROLE_JUNIOR_CHEF)->id,
        ], $overrides));

        foreach ($lines ?? [['item' => $this->item(), 'quantity' => 2, 'unit_price' => 4.20]] as $line) {
            $meal->lines()->create([
                'inventory_item_id' => $line['item']->id,
                'item'              => $line['item']->name,
                'unit'              => $line['item']->unit,
                'quantity'          => $line['quantity'],
                'unit_price'        => $line['unit_price'],
            ]);
        }

        return $meal->load('lines');
    }

    /** What the form posts. One line unless the caller says otherwise. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'meal_date'    => now()->toDateString(),
            'dish'         => 'Nasi goreng kampung',
            'misc_percent' => 30,
            'lines'        => [
                ['inventory_item_id' => $this->item()->id, 'quantity' => 2, 'unit_price' => 4.20],
            ],
        ], $overrides);
    }

    // ---------- who gets in ----------

    public static function rolesThatRecord(): array
    {
        return [
            'owner'       => [User::ROLE_OWNER],
            'head chef'   => [User::ROLE_HEAD_CHEF],
            'junior chef' => [User::ROLE_JUNIOR_CHEF],
            'admin'       => [User::ROLE_ADMIN],
        ];
    }

    #[DataProvider('rolesThatRecord')]
    public function test_they_can_open_the_page_and_record_a_meal(string $role): void
    {
        $this->actingAs($this->user($role))->get(route('staff-meals.index'))->assertOk();

        $this->actingAs($this->user($role))
            ->post(route('staff-meals.store'), $this->payload())
            ->assertRedirect();

        $meal = StaffMeal::latest('id')->firstOrFail();
        $this->assertEquals(8.40, $meal->total);   // 2 × 4.20, derived
    }

    public function test_a_part_timer_is_kept_out_entirely(): void
    {
        $partTimer = $this->user(User::ROLE_PART_TIMER);

        $this->actingAs($partTimer)->get(route('staff-meals.index'))->assertForbidden();
        $this->actingAs($partTimer)->post(route('staff-meals.store'), $this->payload())->assertForbidden();

        $meal = $this->meal();
        $this->actingAs($partTimer)->delete(route('staff-meals.destroy', $meal))->assertForbidden();

        $this->assertDatabaseCount('staff_meals', 1);
    }

    // ---------- what it does to stock ----------

    public function test_recording_a_meal_takes_its_lines_off_stock(): void
    {
        $rice = $this->item('Beras wangi', onHand: 10, cost: 4.20);

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->post(route('staff-meals.store'), $this->payload([
                'lines' => [['inventory_item_id' => $rice->id, 'quantity' => 3, 'unit_price' => 4.20]],
            ]))
            ->assertRedirect();

        $this->assertEquals(7, $rice->fresh()->quantity_on_hand);
    }

    public function test_an_ingredient_listed_twice_is_deducted_once_for_the_total(): void
    {
        // A sheet legitimately names the same thing in two steps. Deducted one
        // line at a time, the first write would be lost to the second.
        $oil = $this->item('Minyak masak', onHand: 5, cost: 8.00);

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->post(route('staff-meals.store'), $this->payload([
                'lines' => [
                    ['inventory_item_id' => $oil->id, 'quantity' => 1.5, 'unit_price' => 8.00],
                    ['inventory_item_id' => $oil->id, 'quantity' => 0.5, 'unit_price' => 8.00],
                ],
            ]))
            ->assertRedirect();

        $this->assertEquals(3, $oil->fresh()->quantity_on_hand);
    }

    public function test_the_deduction_never_takes_stock_below_zero(): void
    {
        $rice = $this->item('Beras wangi', onHand: 1, cost: 4.20);

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->post(route('staff-meals.store'), $this->payload([
                'lines' => [['inventory_item_id' => $rice->id, 'quantity' => 4, 'unit_price' => 4.20]],
            ]))
            ->assertRedirect();

        $this->assertEquals(0, $rice->fresh()->quantity_on_hand);
    }

    public function test_feeding_the_team_does_not_reprice_anything(): void
    {
        // unit_cost is what the ingredient cost to buy. Eating it does not
        // change that, so no dish on the menu is repriced by a staff meal.
        $rice = $this->item('Beras wangi', onHand: 10, cost: 4.20);

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->post(route('staff-meals.store'), $this->payload([
                // Bought cheap that morning — the line price, not the shelf's.
                'lines' => [['inventory_item_id' => $rice->id, 'quantity' => 2, 'unit_price' => 3.00]],
            ]))
            ->assertRedirect();

        $this->assertEquals(4.20, $rice->fresh()->unit_cost);
    }

    // ---------- the costing sheet ----------

    public function test_every_figure_below_the_lines_is_derived(): void
    {
        $meal = $this->meal(['misc_percent' => 30], [
            ['item' => $this->item('Beras wangi', cost: 4.20), 'quantity' => 2,  'unit_price' => 4.20],
            ['item' => $this->item('Telur', cost: 0.55),       'quantity' => 12, 'unit_price' => 0.55],
        ]);

        $this->assertEquals(15.00, $meal->total);        // 8.40 + 6.60
        $this->assertEquals(4.50, $meal->misc_amount);   // 30%
        $this->assertEquals(19.50, $meal->grand_total);

        // Nothing above is stored — a column would be a second place for the
        // sheet's own arithmetic to disagree with itself.
        $this->assertArrayNotHasKey('grand_total', $meal->getAttributes());
    }

    public function test_a_line_is_snapshotted_off_the_item_not_the_form(): void
    {
        $item = $this->item('Ayam peha', cost: 11.70);

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->post(route('staff-meals.store'), $this->payload([
                'lines' => [[
                    'inventory_item_id' => $item->id,
                    'quantity'          => 1.5,
                    'unit_price'        => 11.70,
                    // Posted and ignored: the request does not accept them.
                    'item'              => 'Wagyu',
                    'unit'              => 'g',
                ]],
            ]))
            ->assertRedirect();

        $line = StaffMeal::latest('id')->firstOrFail()->lines()->firstOrFail();

        $this->assertSame('Ayam peha', $line->item);
        $this->assertSame('kg', $line->unit);
    }

    public function test_a_sheet_needs_at_least_one_ingredient(): void
    {
        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->post(route('staff-meals.store'), $this->payload(['lines' => []]))
            ->assertSessionHasErrors('lines');

        $this->assertDatabaseCount('staff_meals', 0);
    }

    // ---------- editing ----------

    public function test_editing_replaces_the_whole_sheet(): void
    {
        $rice = $this->item('Beras wangi', onHand: 10, cost: 4.20);
        $meal = $this->meal([], [
            ['item' => $rice,                   'quantity' => 2, 'unit_price' => 4.20],
            ['item' => $this->item('Telur'),    'quantity' => 12, 'unit_price' => 0.55],
        ]);

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->patch(route('staff-meals.update', $meal), $this->payload([
                'dish'  => 'Mee goreng',
                'lines' => [['inventory_item_id' => $rice->id, 'quantity' => 1, 'unit_price' => 4.20]],
            ]))
            ->assertRedirect();

        $meal->refresh()->load('lines');

        $this->assertSame('Mee goreng', $meal->dish);
        // A line removed in the modal actually goes.
        $this->assertCount(1, $meal->lines);
        // An edit moves no stock in either direction — not a second deduction
        // for the line that stayed, and nothing put back for the one that went.
        // The sheet above was written straight to the database, so the shelf
        // is untouched at 10 and must still be.
        $this->assertEquals(10, $rice->fresh()->quantity_on_hand);
    }

    public function test_editing_a_recorded_meal_does_not_deduct_a_second_time(): void
    {
        $rice = $this->item('Beras wangi', onHand: 10, cost: 4.20);
        $user = $this->user(User::ROLE_HEAD_CHEF);

        // Recorded through the controller, so this one really did move stock.
        $this->actingAs($user)->post(route('staff-meals.store'), $this->payload([
            'lines' => [['inventory_item_id' => $rice->id, 'quantity' => 2, 'unit_price' => 4.20]],
        ]))->assertRedirect();

        $this->assertEquals(8, $rice->fresh()->quantity_on_hand);

        $meal = StaffMeal::latest('id')->firstOrFail();

        // Correcting the quantity upward deducts nothing further: the shelf is
        // corrected with a Tally, which is what the modal tells the chef.
        $this->actingAs($user)->patch(route('staff-meals.update', $meal), $this->payload([
            'lines' => [['inventory_item_id' => $rice->id, 'quantity' => 5, 'unit_price' => 4.20]],
        ]))->assertRedirect();

        $this->assertEquals(8, $rice->fresh()->quantity_on_hand);
    }

    public function test_deleting_a_meal_takes_its_lines_with_it_but_not_the_stock(): void
    {
        $rice = $this->item('Beras wangi', onHand: 10, cost: 4.20);
        $user = $this->user(User::ROLE_HEAD_CHEF);

        $this->actingAs($user)->post(route('staff-meals.store'), $this->payload([
            'lines' => [['inventory_item_id' => $rice->id, 'quantity' => 2, 'unit_price' => 4.20]],
        ]))->assertRedirect();

        $this->actingAs($user)
            ->delete(route('staff-meals.destroy', StaffMeal::latest('id')->firstOrFail()))
            ->assertRedirect();

        $this->assertDatabaseCount('staff_meals', 0);
        $this->assertDatabaseCount('staff_meal_lines', 0);
        // The team still ate it. Same as deleting an R&D sheet or a production
        // batch — the success message says so rather than leaving it a surprise.
        $this->assertEquals(8, $rice->fresh()->quantity_on_hand);
    }

    // ---------- the rendered line editor ----------

    public function test_the_rendered_sheet_script_parses(): void
    {
        // An ingredient name is user input and lands inside a <script> block.
        // A stray backtick or quote kills the rest of the script on a page
        // that still returns 200, so this is checked rather than eyeballed.
        $this->item('Back`tick ${danger} </script> chilli');

        $html = $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->get(route('staff-meals.index'))
            ->assertOk()
            ->getContent();

        preg_match_all('#<script>(.*?)</script>#s', $html, $matches);
        $script = collect($matches[1])->first(fn ($s) => str_contains($s, 'addStaffMealLine'));

        $this->assertNotNull($script, 'The staff meal line editor script was not rendered at all.');

        $file = tempnam(sys_get_temp_dir(), 'meal') . '.js';
        file_put_contents($file, $script);
        exec('node --check ' . escapeshellarg($file) . ' 2>&1', $output, $status);
        @unlink($file);

        $this->assertSame(0, $status, 'Rendered staff meal script is not valid JS: ' . implode(' | ', $output));
    }

    // ---------- the report ----------

    public function test_the_page_reports_spend_per_month(): void
    {
        $this->meal(['meal_date' => now()->startOfMonth()->toDateString()]);
        $this->meal(['meal_date' => now()->subMonthNoOverflow()->startOfMonth()->toDateString()]);

        $this->actingAs($this->user(User::ROLE_OWNER))
            ->get(route('staff-meals.index'))
            ->assertOk()
            ->assertSee('Spent per month')
            ->assertSee(now()->format('F Y'))
            ->assertSee(now()->subMonthNoOverflow()->format('F Y'));
    }

    public function test_a_manager_can_export_the_report(): void
    {
        $this->meal();

        $this->actingAs($this->user(User::ROLE_OWNER))
            ->get(route('staff-meals.export-pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->user(User::ROLE_JUNIOR_CHEF))
            ->get(route('staff-meals.export-pdf'))
            ->assertForbidden();
    }
}
