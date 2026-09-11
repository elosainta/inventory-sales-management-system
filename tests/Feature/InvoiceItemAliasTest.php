<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InvoiceItemAlias;
use App\Models\InvoiceScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The dictionary between a supplier's wording and this kitchen's shelf.
 *
 * The value of this feature is entirely in what it remembers, so the tests
 * that earn their place are the ones about *what gets learned*: that folding
 * case and punctuation actually makes the next invoice match, that a
 * correction wins over the first guess, and — the one that matters most — that
 * an unticked line teaches nothing. A dictionary that silently learns the
 * wrong thing is worse than no dictionary, because every later invoice
 * inherits the mistake without anybody looking.
 */
class InvoiceItemAliasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Http::preventStrayRequests();

        config([
            'services.anthropic.key'            => 'test-key',
            'services.bukku.token'              => 'test-token',
            'services.bukku.default_account_id' => 33,
        ]);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => User::ROLE_OWNER]);
    }

    private function item(string $name): InventoryItem
    {
        return InventoryItem::create([
            'name'             => $name,
            'category'         => 'Meat',
            'unit'             => 'kg',
            'quantity_on_hand' => 5,
            'unit_cost'        => 9.0,
        ]);
    }

    private function scan(): InvoiceScan
    {
        return InvoiceScan::create([
            'user_id'           => $this->manager()->id,
            'file_path'         => 'invoice-scans/x.jpg',
            'original_filename' => 'x.jpg',
            'status'            => InvoiceScan::STATUS_SCANNED,
            'extracted'         => ['lines' => [['description' => 'AYAM PEHA 1KG', 'quantity' => 2, 'unit_price' => 9, 'amount' => 18]]],
        ]);
    }

    private function fakeBukku(): void
    {
        Http::fake([
            '*/products/*'      => Http::response(['product' => []]),
            '*/locations*'      => Http::response(['locations' => []]),
            '*/files'           => Http::response(['file' => ['id' => 1]]),
            '*/purchases/bills' => Http::response(['transaction' => ['id' => 90, 'number' => 'BL-00090']]),
        ]);
    }

    /** @return array<string,mixed> */
    private function payload(array $lineOverrides = []): array
    {
        return [
            'contact_id'   => 2,
            'invoice_date' => '2026-09-03',
            'term_id'      => 3,
            'lines'        => [array_merge([
                'description' => 'AYAM PEHA 1KG',
                'quantity'    => 2,
                'unit_price'  => 9,
                'account_id'  => 33,
                'include'     => '1',
            ], $lineOverrides)],
        ];
    }

    public function test_case_punctuation_and_spacing_fold_to_one_key(): void
    {
        $this->assertSame(
            InvoiceItemAlias::normalise('AYAM PEHA 1KG'),
            InvoiceItemAlias::normalise('  Ayam   Peha (1kg)  '),
        );

        // Different products must not collapse together.
        $this->assertNotSame(
            InvoiceItemAlias::normalise('Ayam Peha'),
            InvoiceItemAlias::normalise('Ayam Dada'),
        );
    }

    public function test_matching_a_line_teaches_the_wording(): void
    {
        $chicken = $this->item('Chicken Thigh');
        $this->fakeBukku();

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.push', $this->scan()), $this->payload([
                'inventory_item_id' => $chicken->id,
            ]))
            ->assertRedirect();

        $alias = InvoiceItemAlias::firstOrFail();

        $this->assertSame('ayam peha 1kg', $alias->normalised);
        $this->assertSame('AYAM PEHA 1KG', $alias->supplier_text);
        $this->assertSame($chicken->id, $alias->inventory_item_id);
    }

    public function test_the_next_invoice_arrives_already_matched(): void
    {
        $chicken = $this->item('Chicken Thigh');
        InvoiceItemAlias::remember('AYAM PEHA 1KG', $chicken->id);

        // Written differently on the next invoice — same thing.
        $matches = InvoiceItemAlias::matchAll(['ayam  peha (1KG)']);

        $this->assertSame(
            $chicken->id,
            $matches[InvoiceItemAlias::normalise('ayam  peha (1KG)')] ?? null,
        );
    }

    /**
     * The one that stops the dictionary rotting.
     *
     * A reviewer who matched a wording wrong must be able to fix it, and the
     * fix has to win — otherwise the unique key would simply reject the
     * correction and the wrong answer would stand forever.
     */
    public function test_a_correction_overwrites_the_first_answer(): void
    {
        $wrong = $this->item('Chicken Breast');
        $right = $this->item('Chicken Thigh');

        InvoiceItemAlias::remember('AYAM PEHA 1KG', $wrong->id);
        InvoiceItemAlias::remember('Ayam Peha 1kg', $right->id);

        $this->assertSame(1, InvoiceItemAlias::count());
        $this->assertSame($right->id, InvoiceItemAlias::firstOrFail()->inventory_item_id);
    }

    public function test_a_rejected_line_is_neither_billed_nor_learned(): void
    {
        $chicken = $this->item('Chicken Thigh');
        $this->fakeBukku();

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.push', $this->scan()), $this->payload([
                'inventory_item_id' => $chicken->id,
                'include'           => '0',
            ]))
            ->assertSessionHasErrors('lines');   // nothing left to bill

        $this->assertSame(0, InvoiceItemAlias::count());
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/purchases/bills'));
    }

    public function test_an_unmatched_line_still_bills_and_teaches_nothing(): void
    {
        $this->fakeBukku();

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.push', $this->scan()), $this->payload())
            ->assertRedirect();

        $this->assertSame(0, InvoiceItemAlias::count());
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), '/purchases/bills'));
    }

    public function test_a_line_cannot_be_matched_to_an_item_that_does_not_exist(): void
    {
        $this->actingAs($this->manager())
            ->post(route('invoice-scan.push', $this->scan()), $this->payload([
                'inventory_item_id' => 99999,
            ]))
            ->assertSessionHasErrors('lines.0.inventory_item_id');

        $this->assertSame(0, InvoiceItemAlias::count());
    }

    public function test_deleting_the_inventory_item_removes_the_alias_rather_than_leaving_it_pointing_nowhere(): void
    {
        $chicken = $this->item('Chicken Thigh');
        InvoiceItemAlias::remember('AYAM PEHA 1KG', $chicken->id);

        $chicken->delete();

        $this->assertSame(0, InvoiceItemAlias::count());
    }

    public function test_the_review_screen_shows_the_match_it_already_knows(): void
    {
        $chicken = $this->item('Chicken Thigh');
        InvoiceItemAlias::remember('AYAM PEHA 1KG', $chicken->id);

        Http::fake([
            '*/contacts*' => Http::response(['contacts' => [['id' => 2, 'legal_name' => 'A Supplier']], 'paging' => ['total' => 1]]),
            '*/accounts*' => Http::response(['accounts' => [['id' => 33, 'code' => '6508', 'name' => 'General Expense']]]),
            '*/products*' => Http::response(['products' => [], 'paging' => ['total' => 0]]),
        ]);

        $this->actingAs($this->manager())
            ->get(route('invoice-scan.show', $this->scan()))
            ->assertOk()
            ->assertSee('Your inventory item')
            ->assertSee('value="' . $chicken->id . '"', false);
    }

    /**
     * The review screen builds rows in JS and the picker's inventory payload is
     * Blade output dropped into the same page. An item named with a backtick or
     * a ${ would end a template literal early and kill the rest of the script —
     * on a page that still returns 200, so nothing looks wrong until a reviewer
     * finds the match boxes dead.
     */
    public function test_the_rendered_review_script_parses(): void
    {
        if (! shell_exec('node --version 2>&1')) {
            $this->markTestSkipped('node is not on PATH.');
        }

        $this->item('Back`tick ${danger} </script> chilli');

        Http::fake([
            '*/contacts*' => Http::response(['contacts' => [['id' => 2, 'legal_name' => 'A Supplier']], 'paging' => ['total' => 1]]),
            '*/accounts*' => Http::response(['accounts' => [['id' => 33, 'code' => '6508', 'name' => 'General Expense']]]),
            '*/products*' => Http::response(['products' => [], 'paging' => ['total' => 0]]),
        ]);

        $html = $this->actingAs($this->manager())
            ->get(route('invoice-scan.show', $this->scan()))
            ->assertOk()
            ->getContent();

        preg_match_all('#<script>(.*?)</script>#s', $html, $matches);

        foreach (['addLine', 'ItemPicker'] as $needle) {
            $script = collect($matches[1])->first(fn ($s) => str_contains($s, $needle));
            $this->assertNotNull($script, "The script containing {$needle} was not rendered at all.");

            $file = tempnam(sys_get_temp_dir(), 'inv') . '.js';
            file_put_contents($file, $script);
            exec('node --check ' . escapeshellarg($file) . ' 2>&1', $output, $status);
            @unlink($file);

            $this->assertSame(0, $status, "Rendered {$needle} script is not valid JS: " . implode(' | ', $output));
            $output = [];
        }
    }
}
