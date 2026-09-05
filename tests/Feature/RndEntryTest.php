<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Recipe;
use App\Models\RndEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * R&D trials, recorded as costing sheets: chefs, Admin and the Owner record and
 * correct them; the Owner alone approves or rejects.
 *
 * Three rules carry the weight here, and all three are enforced in the
 * controller rather than by hiding a button:
 *
 *   An APPROVED entry is frozen. If it were not, someone could change the
 *   price after the Owner signed off and the approval would mean nothing.
 *
 *   A REJECTED entry is editable, and editing it sends it back to pending.
 *   The point of a rejection is that it can be corrected; leaving it rejected
 *   with new figures would mean the Owner's decision described numbers that
 *   are no longer there.
 *
 *   Every line's name and unit are snapshotted off the picked inventory item,
 *   never off the form, so a sheet cannot read as one ingredient while having
 *   deducted another.
 */
class RndEntryTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_demo' => false]);
    }

    private function item(string $name = 'Yuzu koshō', float $onHand = 10, float $cost = 18.50): InventoryItem
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
    private function entry(string $status = RndEntry::STATUS_PENDING, array $overrides = [], ?array $lines = null): RndEntry
    {
        $entry = RndEntry::create(array_merge([
            'menu_name'      => 'Yuzu dressing',
            'serving_size'   => 1,
            'misc_percent'   => 30,
            'selling_price'  => null,
            'remark'         => 'Trying it in the dressing',
            'purchased_on'   => now()->toDateString(),
            'status'         => $status,
            'created_by'     => $this->user(User::ROLE_JUNIOR_CHEF)->id,
        ], $overrides));

        foreach ($lines ?? [['item' => $this->item(), 'quantity' => 2, 'unit_price' => 18.50]] as $line) {
            $entry->lines()->create([
                'inventory_item_id' => $line['item']->id,
                'item'              => $line['item']->name,
                'unit'              => $line['item']->unit,
                'quantity'          => $line['quantity'],
                'unit_price'        => $line['unit_price'],
            ]);
        }

        return $entry->load('lines');
    }

    /** What the form posts. One line unless the caller says otherwise. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'menu_name'      => 'Yuzu dressing',
            'serving_size'   => 1,
            'misc_percent'   => 30,
            'remark'         => 'Trying it in the dressing',
            'purchased_on'   => now()->toDateString(),
            'lines'          => [
                ['inventory_item_id' => $this->item()->id, 'quantity' => 2, 'unit_price' => 18.50],
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
    public function test_they_can_open_the_page_and_record_a_trial(string $role): void
    {
        $this->actingAs($this->user($role))->get(route('rnd.index'))->assertOk();

        $this->actingAs($this->user($role))
            ->post(route('rnd.store'), $this->payload())
            ->assertRedirect();

        $entry = RndEntry::latest('id')->firstOrFail();
        $this->assertSame(RndEntry::STATUS_PENDING, $entry->status);
        $this->assertEquals(37.00, $entry->total);   // 2 × 18.50, derived
    }

    public function test_a_part_timer_is_kept_out_entirely(): void
    {
        // Every other priced page excludes them; this one shows what things cost.
        $part = $this->user(User::ROLE_PART_TIMER);

        $this->actingAs($part)->get(route('rnd.index'))->assertForbidden();
        $this->actingAs($part)->post(route('rnd.store'), $this->payload())->assertForbidden();
        $this->assertSame(0, RndEntry::count());
    }

    // ---------- the costing sheet ----------

    public function test_a_trial_is_a_whole_sheet_of_ingredients(): void
    {
        // The point of the change: a dish is developed out of a dozen things at
        // once, and keyed in one at a time no line ever adds up to the plate.
        $pork  = $this->item('Minced pork', onHand: 20, cost: 20.50);
        $basil = $this->item('Thai basil', onHand: 5, cost: 12.00);
        $rice  = $this->item('Rice', onHand: 50, cost: 59.00);

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->post(route('rnd.store'), $this->payload([
                'menu_name'     => 'Pad kra pao pork',
                'misc_percent'  => 30,
                'selling_price' => 25.00,
                'lines'         => [
                    ['inventory_item_id' => $pork->id,  'quantity' => 0.1,  'unit_price' => 20.50],
                    ['inventory_item_id' => $basil->id, 'quantity' => 0.01, 'unit_price' => 12.00],
                    ['inventory_item_id' => $rice->id,  'quantity' => 0.3,  'unit_price' => 59.00],
                ],
            ]))
            ->assertRedirect();

        $entry = RndEntry::latest('id')->firstOrFail()->load('lines');

        $this->assertCount(3, $entry->lines);

        // 2.05 + 0.12 + 17.70
        $this->assertEquals(19.87, $entry->total);
        $this->assertEquals(5.96, $entry->misc_amount);       // 30% of 19.87
        $this->assertEquals(25.83, $entry->grand_total);
        $this->assertEquals(-0.83, $entry->profit);           // 25.00 − 25.83
    }

    public function test_the_profit_is_blank_until_a_selling_price_is_set(): void
    {
        // A trial does not always have a price yet, and showing RM 0.00 profit
        // for one that has not been priced would read as a break-even dish.
        $this->assertNull($this->entry()->profit);
    }

    public function test_a_sheet_needs_at_least_one_ingredient(): void
    {
        $payload = $this->payload();
        unset($payload['lines']);

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->post(route('rnd.store'), $payload)
            ->assertSessionHasErrors('lines');

        $this->assertSame(0, RndEntry::count());
    }

    public function test_the_menu_name_is_required(): void
    {
        $payload = $this->payload();
        unset($payload['menu_name']);

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->post(route('rnd.store'), $payload)
            ->assertSessionHasErrors('menu_name');

        $this->assertSame(0, RndEntry::count());
    }

    // ---------- who decides ----------

    public function test_only_the_owner_approves_or_rejects(): void
    {
        foreach ([User::ROLE_HEAD_CHEF, User::ROLE_JUNIOR_CHEF, User::ROLE_ADMIN] as $role) {
            $entry = $this->entry();

            $this->actingAs($this->user($role))
                ->patch(route('rnd.approve', $entry))
                ->assertForbidden();

            $this->actingAs($this->user($role))
                ->patch(route('rnd.reject', $entry))
                ->assertForbidden();

            $this->assertSame(RndEntry::STATUS_PENDING, $entry->fresh()->status);
        }
    }

    public function test_admin_is_excluded_from_deciding_despite_the_blanket_grant(): void
    {
        // Admin reaches every feature but the dashboard — except this one
        // ability, which is in AppServiceProvider::ADMIN_EXCEPT so it falls
        // through to its own closure. Asserted separately from the loop above
        // because it is the only gate the blanket grant is asked to skip.
        $admin = $this->user(User::ROLE_ADMIN);

        $this->assertTrue($admin->can('view-rnd'));
        $this->assertTrue($admin->can('manage-rnd'));
        $this->assertFalse($admin->can('decide-rnd'));
    }

    public function test_the_owner_approves_and_the_decision_is_stamped(): void
    {
        $owner = $this->user(User::ROLE_OWNER);
        $entry = $this->entry();

        $this->actingAs($owner)->patch(route('rnd.approve', $entry))->assertRedirect();

        $entry->refresh();
        $this->assertSame(RndEntry::STATUS_APPROVED, $entry->status);
        $this->assertSame($owner->id, $entry->decided_by);
        $this->assertNotNull($entry->decided_at);
    }

    public function test_a_decided_entry_cannot_be_decided_again(): void
    {
        $owner = $this->user(User::ROLE_OWNER);
        $entry = $this->entry(RndEntry::STATUS_APPROVED);

        $this->actingAs($owner)->patch(route('rnd.reject', $entry))->assertSessionHas('error');

        $this->assertSame(RndEntry::STATUS_APPROVED, $entry->fresh()->status);
    }

    // ---------- the lock ----------

    public function test_an_approved_entry_cannot_be_edited_or_deleted(): void
    {
        $entry = $this->entry(RndEntry::STATUS_APPROVED);
        $chef  = $this->user(User::ROLE_HEAD_CHEF);

        $cheap = $this->payload();
        $cheap['lines'][0]['unit_price'] = 1.00;

        $this->actingAs($chef)
            ->patch(route('rnd.update', $entry), $cheap)
            ->assertSessionHas('error');

        $this->actingAs($chef)->delete(route('rnd.destroy', $entry))->assertSessionHas('error');

        $entry->refresh()->load('lines');
        $this->assertEquals(18.50, $entry->lines->first()->unit_price);
        $this->assertSame(RndEntry::STATUS_APPROVED, $entry->status);
    }

    public function test_editing_a_rejected_entry_sends_it_back_to_the_owner(): void
    {
        $owner = $this->user(User::ROLE_OWNER);
        $entry = $this->entry(RndEntry::STATUS_REJECTED, [
            'decided_by' => $owner->id,
            'decided_at' => now(),
        ]);

        $corrected = $this->payload();
        $corrected['lines'][0]['unit_price'] = 12.00;

        $this->actingAs($this->user(User::ROLE_JUNIOR_CHEF))
            ->patch(route('rnd.update', $entry), $corrected)
            ->assertRedirect();

        $entry->refresh()->load('lines');
        $this->assertSame(RndEntry::STATUS_PENDING, $entry->status);
        $this->assertEquals(12.00, $entry->lines->first()->unit_price);
        $this->assertNull($entry->decided_by);
        $this->assertNull($entry->decided_at);
    }

    public function test_editing_replaces_the_whole_sheet(): void
    {
        // An edit is a re-keyed sheet, not a merge: lines removed in the modal
        // have to actually go, or a corrected trial keeps the ingredient the
        // correction was there to take off it.
        $entry = $this->entry();
        $oil   = $this->item('Cooking oil');

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->patch(route('rnd.update', $entry), $this->payload([
                'lines' => [['inventory_item_id' => $oil->id, 'quantity' => 0.02, 'unit_price' => 30.90]],
            ]))
            ->assertRedirect();

        $entry->refresh()->load('lines');
        $this->assertCount(1, $entry->lines);
        $this->assertSame('Cooking oil', $entry->lines->first()->item);
        $this->assertSame(RndEntry::STATUS_PENDING, $entry->status);
    }

    // ---------- the form is not the boundary ----------

    public function test_a_posted_status_is_ignored(): void
    {
        // Otherwise a chef approves their own spending with one extra field.
        $this->actingAs($this->user(User::ROLE_JUNIOR_CHEF))
            ->post(route('rnd.store'), $this->payload([
                'status'     => RndEntry::STATUS_APPROVED,
                'decided_by' => 1,
            ]))
            ->assertRedirect();

        $entry = RndEntry::latest('id')->firstOrFail();
        $this->assertSame(RndEntry::STATUS_PENDING, $entry->status);
        $this->assertNull($entry->decided_by);
    }

    public function test_anyone_with_access_may_correct_anyone_elses_entry(): void
    {
        // The Owner's call: no ownership check. The audit trail records who
        // changed what, and a chef correcting a colleague's typo is the
        // common case in a kitchen where one person does the buying.
        $entry = $this->entry();
        $other = $this->item('Corrected');

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->patch(route('rnd.update', $entry), $this->payload([
                'lines' => [['inventory_item_id' => $other->id, 'quantity' => 1, 'unit_price' => 2]],
            ]))
            ->assertRedirect();

        $this->assertSame('Corrected', $entry->fresh()->lines->first()->item);
    }

    // ---------- stock ----------

    public function test_recording_takes_every_line_off_stock_straight_away(): void
    {
        // The chef has already taken it off the shelf. Stock that waits for a
        // review is stock nobody can trust.
        $koshō = $this->item('Yuzu koshō', onHand: 10);
        $oil   = $this->item('Cooking oil', onHand: 4, cost: 30.90);

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->post(route('rnd.store'), $this->payload([
                'lines' => [
                    ['inventory_item_id' => $koshō->id, 'quantity' => 2.5,  'unit_price' => 18.50],
                    ['inventory_item_id' => $oil->id,   'quantity' => 0.02, 'unit_price' => 30.90],
                ],
            ]))
            ->assertRedirect();

        $this->assertEquals(7.5, $koshō->fresh()->quantity_on_hand);
        $this->assertEquals(3.98, $oil->fresh()->quantity_on_hand);

        // Derived from the quantity by InventoryItem::booted(), in decimal.
        $this->assertEquals(138.75, $koshō->fresh()->monetary_value);

        // Cooking with an ingredient does not change what it cost to buy, so
        // no recipe is repriced by an experiment.
        $this->assertEquals(18.50, $koshō->fresh()->unit_cost);
    }

    public function test_the_same_ingredient_on_two_lines_is_deducted_once_for_the_sum(): void
    {
        // A sheet legitimately lists oil twice (two steps). Deducting those one
        // at a time off a stale model would lose the first.
        $oil = $this->item('Cooking oil', onHand: 10);

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->post(route('rnd.store'), $this->payload([
                'lines' => [
                    ['inventory_item_id' => $oil->id, 'quantity' => 2, 'unit_price' => 30.90],
                    ['inventory_item_id' => $oil->id, 'quantity' => 3, 'unit_price' => 30.90],
                ],
            ]))
            ->assertRedirect();

        $this->assertEquals(5, $oil->fresh()->quantity_on_hand);
        $this->assertCount(2, RndEntry::latest('id')->firstOrFail()->lines);
    }

    public function test_the_name_and_unit_are_snapshotted_off_the_item_not_the_form(): void
    {
        // Otherwise a line could read as one ingredient while having deducted
        // another — and it has to survive that ingredient being renamed.
        $item = $this->item('Yuzu koshō');

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->post(route('rnd.store'), $this->payload([
                'lines' => [[
                    'inventory_item_id' => $item->id,
                    'quantity'          => 1,
                    'unit_price'        => 5,
                    'item'              => 'Something else entirely',
                    'unit'              => 'truckload',
                ]],
            ]))
            ->assertRedirect();

        $line = RndEntry::latest('id')->firstOrFail()->lines->first();
        $this->assertSame('Yuzu koshō', $line->item);
        $this->assertSame('kg', $line->unit);

        $item->update(['name' => 'Yuzu paste']);
        $this->assertSame('Yuzu koshō', $line->fresh()->item);
    }

    public function test_stock_never_goes_below_zero(): void
    {
        $item = $this->item('Yuzu koshō', onHand: 1);

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->post(route('rnd.store'), $this->payload([
                'lines' => [['inventory_item_id' => $item->id, 'quantity' => 5, 'unit_price' => 1]],
            ]))
            ->assertRedirect();

        $this->assertEquals(0, $item->fresh()->quantity_on_hand);
    }

    public function test_editing_rejecting_and_deleting_do_not_move_stock_again(): void
    {
        // The same rule as Sales, Wastage and Production: a deduction is not
        // reversed and not repeated. A second deduction on every correction
        // would make one typo cost real stock twice. That now covers a line
        // ADDED by an edit as well — it deducts nothing, and the modal says so.
        $item  = $this->item('Yuzu koshō', onHand: 10);
        $extra = $this->item('Cooking oil', onHand: 10);
        $chef  = $this->user(User::ROLE_HEAD_CHEF);

        $this->actingAs($chef)->post(route('rnd.store'), $this->payload([
            'lines' => [['inventory_item_id' => $item->id, 'quantity' => 2, 'unit_price' => 1]],
        ]))->assertRedirect();

        $this->assertEquals(8, $item->fresh()->quantity_on_hand);

        $entry = RndEntry::latest('id')->firstOrFail();

        $this->actingAs($chef)->patch(route('rnd.update', $entry), $this->payload([
            'lines' => [
                ['inventory_item_id' => $item->id,  'quantity' => 6, 'unit_price' => 1],
                ['inventory_item_id' => $extra->id, 'quantity' => 4, 'unit_price' => 1],
            ],
        ]))->assertRedirect();
        $this->assertEquals(8, $item->fresh()->quantity_on_hand);
        $this->assertEquals(10, $extra->fresh()->quantity_on_hand);

        $this->actingAs($this->user(User::ROLE_OWNER))
            ->patch(route('rnd.reject', $entry))->assertRedirect();
        $this->assertEquals(8, $item->fresh()->quantity_on_hand);

        $this->actingAs($chef)->delete(route('rnd.destroy', $entry))->assertRedirect();
        $this->assertEquals(8, $item->fresh()->quantity_on_hand);
    }

    public function test_deleting_a_sheet_takes_its_lines_with_it(): void
    {
        $entry = $this->entry();

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->delete(route('rnd.destroy', $entry))
            ->assertRedirect();

        $this->assertDatabaseCount('rnd_entry_lines', 0);
    }

    public function test_a_part_timer_cannot_move_stock_through_this_page(): void
    {
        // The gate is the boundary, but this is the consequence worth naming:
        // the page writes to live inventory.
        $item = $this->item('Yuzu koshō', onHand: 10);

        $this->actingAs($this->user(User::ROLE_PART_TIMER))
            ->post(route('rnd.store'), $this->payload([
                'lines' => [['inventory_item_id' => $item->id, 'quantity' => 1, 'unit_price' => 1]],
            ]))
            ->assertForbidden();

        $this->assertEquals(10, $item->fresh()->quantity_on_hand);
    }

    public function test_a_hostile_item_name_does_not_break_the_edit_button(): void
    {
        // The Edit button carries the whole sheet as JSON inside an onclick
        // attribute, and item names are typed by chefs. A quote or a tag out
        // of Blade would end the attribute early and rewrite the page.
        $nasty = $this->item('Chilli " onclick="alert(1)" x="</script><img src=x>');

        $this->entry(RndEntry::STATUS_PENDING, [
            'remark' => '<b>bold</b> & so on',
        ], [['item' => $nasty, 'quantity' => 1, 'unit_price' => 1]]);

        $html = $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->get(route('rnd.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('onclick="alert(1)"', $html);
        $this->assertStringNotContainsString('<img src=x>', $html);
        $this->assertStringNotContainsString('<b>bold</b>', $html);
    }

    public function test_the_rendered_sheet_script_parses(): void
    {
        // The line editor builds rows in JS and the ingredient payload is Blade
        // output dropped into the same <script>. A comma inside a translated
        // string already broke a page this way once — @json splits its argument
        // on commas — and that one 500s rather than failing quietly, but a
        // stray backtick or ${ would not: the page still renders 200 with the
        // rest of the script dead.
        if (! shell_exec('node --version 2>&1')) {
            $this->markTestSkipped('node is not on PATH.');
        }

        $this->item('Back`tick ${danger} </script> chilli');

        $html = $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->get(route('rnd.index'))
            ->assertOk()
            ->getContent();

        preg_match_all('#<script>(.*?)</script>#s', $html, $matches);
        $script = collect($matches[1])->first(fn ($s) => str_contains($s, 'addRndLine'));

        $this->assertNotNull($script, 'The R&D line editor script was not rendered at all.');

        $file = tempnam(sys_get_temp_dir(), 'rnd') . '.js';
        file_put_contents($file, $script);
        exec('node --check ' . escapeshellarg($file) . ' 2>&1', $output, $status);
        @unlink($file);

        $this->assertSame(0, $status, 'Rendered R&D script is not valid JS: ' . implode(' | ', $output));
    }

    // ---------- an approved trial becomes a dish ----------

    public function test_an_approved_trial_becomes_a_recipe_made_of_everything_it_used(): void
    {
        $pork  = $this->item('Minced pork', onHand: 20, cost: 20.50);
        $basil = $this->item('Thai basil', onHand: 5, cost: 12.00);

        $entry = $this->entry(RndEntry::STATUS_APPROVED, ['misc_percent' => 30], [
            ['item' => $pork,  'quantity' => 0.1,  'unit_price' => 20.50],
            ['item' => $basil, 'quantity' => 0.01, 'unit_price' => 12.00],
        ]);

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->post(route('rnd.recipe', $entry), [
                'name'          => 'Pad kra pao pork',
                'serving_size'  => 1,
                'selling_price' => 25.00,
            ])
            ->assertRedirect(route('recipes.index'));

        $recipe = Recipe::where('name', 'Pad kra pao pork')->firstOrFail();

        // The ingredients and quantities come off the sheet, never off the form
        // — otherwise this is an ordinary "new recipe" screen wearing an R&D
        // label. The miscellaneous percentage comes off it too, so the dish is
        // costed the way the trial was.
        $this->assertCount(2, $recipe->ingredients);
        $this->assertEqualsCanonicalizing(
            [$pork->id, $basil->id],
            $recipe->ingredients->pluck('inventory_item_id')->all()
        );
        $this->assertEquals(0.1, $recipe->ingredients->firstWhere('inventory_item_id', $pork->id)->quantity);
        $this->assertEquals(30, $recipe->misc_percent);

        // And the link back, so the trail from spend to dish is readable.
        $this->assertSame($recipe->id, $entry->fresh()->recipe_id);
    }

    public function test_the_menu_name_and_the_ingredients_are_both_shown(): void
    {
        // Two different things: what the trial was FOR, and what it was made
        // WITH. A list of only the second reads as a list of ingredients with
        // no way to tell which experiment each belonged to.
        $item = $this->item('Yuzu koshō');

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->post(route('rnd.store'), $this->payload([
                'menu_name' => 'Charred cabbage',
                'lines'     => [['inventory_item_id' => $item->id, 'quantity' => 1, 'unit_price' => 5]],
            ]))
            ->assertRedirect();

        $entry = RndEntry::latest('id')->firstOrFail();
        $this->assertSame('Charred cabbage', $entry->menu_name);
        $this->assertSame('Yuzu koshō', $entry->lines->first()->item);

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))
            ->get(route('rnd.index'))
            ->assertOk()
            ->assertSee('Charred cabbage', false)
            ->assertSee('Yuzu koshō', false);
    }

    public function test_a_trial_cannot_become_two_recipes(): void
    {
        $entry = $this->entry(RndEntry::STATUS_APPROVED);
        $chef  = $this->user(User::ROLE_HEAD_CHEF);

        $payload = ['name' => 'First', 'serving_size' => 1, 'selling_price' => 10];

        $this->actingAs($chef)->post(route('rnd.recipe', $entry), $payload)->assertRedirect();
        $this->actingAs($chef)->post(route('rnd.recipe', $entry), $payload + ['name' => 'Second'])
            ->assertSessionHas('error');

        $this->assertSame(1, Recipe::count());
    }

    public function test_a_pending_or_rejected_trial_cannot_become_a_recipe(): void
    {
        // The approval is the gate on this. A dish nobody signed off the spend
        // for has no business on the menu.
        $chef = $this->user(User::ROLE_HEAD_CHEF);

        foreach ([RndEntry::STATUS_PENDING, RndEntry::STATUS_REJECTED] as $status) {
            $this->actingAs($chef)
                ->post(route('rnd.recipe', $this->entry($status)), [
                    'name' => 'Too soon', 'serving_size' => 1, 'selling_price' => 10,
                ])
                ->assertSessionHas('error');
        }

        $this->assertSame(0, Recipe::count());
    }

    public function test_a_junior_chef_cannot_write_the_dish_up(): void
    {
        // Recording and approving are one thing; putting a dish on the menu is
        // manage-recipes, which a junior chef does not hold — except Riley.
        $this->actingAs($this->user(User::ROLE_JUNIOR_CHEF))
            ->post(route('rnd.recipe', $this->entry(RndEntry::STATUS_APPROVED)), [
                'name' => 'Nope', 'serving_size' => 1, 'selling_price' => 10,
            ])
            ->assertForbidden();

        $this->assertSame(0, Recipe::count());
    }

    // ---------- the PDF ----------

    public function test_managers_can_export_the_pdf(): void
    {
        $this->entry(RndEntry::STATUS_APPROVED, ['selling_price' => 25.00]);

        $response = $this->actingAs($this->user(User::ROLE_OWNER))->get(route('rnd.export-pdf'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_a_junior_chef_cannot_export_the_pdf(): void
    {
        // export-pdf is isManager, and this sheet carries what everything cost.
        $this->actingAs($this->user(User::ROLE_JUNIOR_CHEF))
            ->get(route('rnd.export-pdf'))
            ->assertForbidden();
    }

    // ---------- spend per menu ----------

    public function test_it_totals_the_grand_total_for_each_menu(): void
    {
        $item = $this->item('Yuzu koshō', onHand: 100);
        $chef = $this->user(User::ROLE_HEAD_CHEF);

        // Two trials on one dish, one on another. 0% miscellaneous keeps the
        // arithmetic here about the grouping rather than the overhead.
        foreach ([['Yuzu dressing', 2, 10.00], ['Yuzu dressing', 1, 5.00], ['Charred cabbage', 3, 4.00]] as [$menu, $qty, $price]) {
            $this->actingAs($chef)->post(route('rnd.store'), $this->payload([
                'menu_name'    => $menu,
                'misc_percent' => 0,
                'lines'        => [['inventory_item_id' => $item->id, 'quantity' => $qty, 'unit_price' => $price]],
            ]))->assertRedirect();
        }

        $this->actingAs($this->user(User::ROLE_OWNER))
            ->get(route('rnd.index'))
            ->assertOk()
            ->assertSee('Spent per menu', false)
            ->assertSee('25.00', false)    // Yuzu dressing: 20 + 5
            ->assertSee('12.00', false);   // Charred cabbage: 3 × 4
    }

    public function test_the_menu_total_includes_the_miscellaneous_overhead(): void
    {
        // The costing sheet's own bottom line is the grand total, so that is
        // what "spent" means on this page.
        $item = $this->item('Yuzu koshō', onHand: 100);

        $this->actingAs($this->user(User::ROLE_HEAD_CHEF))->post(route('rnd.store'), $this->payload([
            'menu_name'    => 'Yuzu dressing',
            'misc_percent' => 30,
            'lines'        => [['inventory_item_id' => $item->id, 'quantity' => 2, 'unit_price' => 10.00]],
        ]))->assertRedirect();

        $entry = RndEntry::latest('id')->firstOrFail()->load('lines');
        $this->assertEquals(20.00, $entry->total);
        $this->assertEquals(26.00, $entry->grand_total);

        $this->actingAs($this->user(User::ROLE_OWNER))
            ->get(route('rnd.index'))
            ->assertOk()
            ->assertSee('26.00', false);
    }
}
