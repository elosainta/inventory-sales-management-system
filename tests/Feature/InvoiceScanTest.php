<?php

namespace Tests\Feature;

use App\Models\InvoiceScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Invoice scan (BETA): web in, Bukku, web out.
 *
 * Two things here are worth a test even in a beta, because getting them wrong
 * costs money rather than time: who is allowed to post to the company's books,
 * and the guard that stops one invoice becoming two bills.
 *
 * Every outbound call is faked. These tests must never reach Anthropic or
 * Bukku — one of them bills per request and the other writes to real accounts.
 */
class InvoiceScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();

        // A pattern that fails to match would otherwise fall through to the
        // real network - and these two APIs bill per request and write to
        // live accounts. Make an unmatched request an error instead.
        Http::preventStrayRequests();

        config([
            'services.anthropic.key'            => 'test-key',
            'services.bukku.token'              => 'test-token',
            'services.bukku.default_account_id' => 33,
        ]);
    }

    /**
     * Product 77 as Bukku actually returns it — stock-tracked, so its account
     * is Inventory (5) and not the purchase account (23).
     */
    private const PRODUCT_77 = [
        'id'                   => 77,
        'name'                 => 'Italian Sweet Basil - Normal 20g',
        'track_inventory'      => true,
        'inventory_account_id' => 5,
        'purchase_account_id'  => 23,
        'units'                => [['id' => 77, 'label' => 'unit', 'is_purchase_default' => true]],
    ];

    private function manager(): User
    {
        return User::factory()->create(['role' => User::ROLE_OWNER]);
    }

    /** The model's reply, shaped the way the schema asks for it. */
    private function fakeScanResponse(array $overrides = []): array
    {
        return [
            'stop_reason' => 'end_turn',
            'content'     => [[
                'type' => 'text',
                'text' => json_encode(array_merge([
                    'supplier_name'  => 'GREEN VALLEY FARM SDN BHD',
                    'invoice_number' => 'INV-20001',
                    'invoice_date'   => '2026-08-28',
                    'currency'       => 'MYR',
                    'lines'          => [
                        ['description' => 'Italian Sweet Basil 20g', 'quantity' => 5, 'unit_price' => 4, 'amount' => 20],
                        ['description' => 'Delivery Fee', 'quantity' => 1, 'unit_price' => 3, 'amount' => 3],
                    ],
                    'subtotal' => 23,
                    'tax'      => 0,
                    'total'    => 23,
                ], $overrides)),
            ]],
        ];
    }

    private function scannedRow(): InvoiceScan
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->fakeScanResponse())]);

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.store'), [
                'invoice' => UploadedFile::fake()->image('invoice.jpg'),
            ]);

        return InvoiceScan::firstOrFail();
    }

    public function test_every_role_can_reach_it(): void
    {
        // Junior chefs and part timers were refused until 2026-09-10, and
        // Admin until 2026-09-03. Reaching the screen is open to every
        // signed-in account now — whoever takes the delivery scans it. What
        // they get is the reading, not the button: Send is `send-invoice-scan`
        // and stayed with the managers.
        foreach ([User::ROLE_JUNIOR_CHEF, User::ROLE_PART_TIMER, User::ROLE_ADMIN] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('invoice-scan.index'))
                ->assertOk();
        }
    }

    public function test_managers_can_reach_it(): void
    {
        foreach ([User::ROLE_OWNER, User::ROLE_HEAD_CHEF] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('invoice-scan.index'))
                ->assertOk()
                ->assertSee('BETA')
                // The camera is a native capture on the file input, so the
                // button and the original accept list must both survive.
                ->assertSee('Take photo')
                ->assertSee('data-accept=".jpg,.jpeg,.png,.webp,.gif,.pdf"', false);
        }
    }

    public function test_the_review_screen_renders_with_what_was_read(): void
    {
        $scan = $this->scannedRow();

        Http::fake([
            '*/contacts*' => Http::response(['contacts' => [['id' => 2, 'name' => 'GREEN VALLEY FARM SDN BHD']]]),
            '*/accounts*' => Http::response(['accounts' => [['id' => 33, 'code' => '6508', 'name' => 'General Expense']]]),
            '*/products*' => Http::response(['products' => [['id' => 77, 'name' => 'Italian Sweet Basil - Normal 20g']]]),
        ]);

        $this->actingAs($this->manager())
            ->get(route('invoice-scan.show', $scan))
            ->assertOk()
            ->assertSee('BETA')
            ->assertSee('GREEN VALLEY FARM SDN BHD')
            ->assertSee('Italian Sweet Basil 20g')   // a line that was read
            ->assertSee('Your inventory item')       // the match column
            ->assertSee('Send to Bukku')
            // The account and Bukku-product pickers were removed on 2026-09-03
            // at the Owner's request; every line now takes the fallback
            // account server-side.
            ->assertDontSee('Stock product (Bukku)');
    }

    public function test_the_review_screen_refuses_to_offer_send_when_bukku_returns_no_suppliers(): void
    {
        $scan = $this->scannedRow();

        Http::fake(['*' => Http::response([], 500)]);

        $this->actingAs($this->manager())
            ->get(route('invoice-scan.show', $scan))
            ->assertOk()
            ->assertSee('No suppliers came back from Bukku');
    }

    /**
     * Scanning and sending are two gates.
     *
     * `use-invoice-scan` is the screen and every account holds it — whoever
     * takes the delivery has the paper in their hand. `send-invoice-scan` is
     * the button that posts a real bill to the books, and it is a manager's.
     * The Form Request carries the same gate, because it authorizes before the
     * controller runs; without that an unauthorised malformed post is a 302
     * rather than a 403, which is the shape this repo has already been bitten
     * by on Profile.
     */
    public function test_a_junior_chef_can_review_a_scan_but_not_send_it(): void
    {
        $scan = $this->scannedRow();

        Http::fake([
            '*/contacts*' => Http::response(['contacts' => [['id' => 2, 'name' => 'GREEN VALLEY FARM SDN BHD']]]),
            '*/accounts*' => Http::response(['accounts' => [['id' => 33, 'code' => '6508', 'name' => 'General Expense']]]),
            '*/products*' => Http::response(['products' => [['id' => 77, 'name' => 'Italian Sweet Basil - Normal 20g']]]),
        ]);

        $chef = User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]);

        $this->actingAs($chef)
            ->get(route('invoice-scan.show', $scan))
            ->assertOk()
            ->assertSee('Italian Sweet Basil 20g')       // they see what was read
            ->assertDontSee('Send to Bukku')             // but not the button
            ->assertSee('A manager sends this to Bukku.')
            // Nor the "add it to inventory" panel: a junior chef does not hold
            // record-inventory, and a panel that 403s on Add is worse than none.
            // Checked on the markup, not the class name — the shared picker
            // script names `.new-item-add` on every page, for every role.
            ->assertDontSee('class="new-item"', false)
            ->assertDontSee('Not in inventory yet', false);

        $this->actingAs($chef)
            ->post(route('invoice-scan.push', $scan), [
                'contact_id'   => 2,
                'invoice_date' => '2026-08-28',
                'term_id'      => 3,
                'lines'        => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1, 'include' => '1', 'account_id' => 33]],
            ])
            ->assertForbidden();

        $this->assertNull($scan->fresh()->bukku_transaction_id);
    }

    /**
     * A line for something the kitchen has never stocked can be matched without
     * leaving the review screen: type the name, pick a category and unit, Add.
     * It is the same panel Purchases has, now shared through partials/item-picker,
     * and the item it creates starts at quantity and cost zero — Send is what
     * puts the first of it on the shelf.
     *
     * The script check is not decoration. The picker now carries the create
     * logic for three pages, and a syntax error in it would kill every match box
     * on all three while the pages still returned 200.
     */
    public function test_a_line_can_be_added_to_inventory_from_the_review_screen(): void
    {
        $scan = $this->scannedRow();

        Http::fake([
            '*/contacts*' => Http::response(['contacts' => [['id' => 2, 'name' => 'GREEN VALLEY FARM SDN BHD']]]),
            '*/accounts*' => Http::response(['accounts' => [['id' => 33, 'code' => '6508', 'name' => 'General Expense']]]),
            '*/products*' => Http::response(['products' => [['id' => 77, 'name' => 'Italian Sweet Basil - Normal 20g']]]),
        ]);

        $html = $this->actingAs($this->manager())
            ->get(route('invoice-scan.show', $scan))
            ->assertOk()
            ->assertSee('Not in inventory yet', false)
            ->assertSee('class="new-item"', false)
            ->getContent();

        // The endpoint the panel posts to answers with the item, at zero.
        $this->actingAs($this->manager())
            ->postJson(route('inventory.store'), [
                'name'              => 'Italian Sweet Basil',
                'category'          => \App\Models\InventoryItem::CATEGORIES[0],
                'unit'              => 'pcs',
                'quantity_on_hand'  => 0,
                'reorder_threshold' => 0,
                'unit_cost'         => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Italian Sweet Basil');

        if (! shell_exec('node --version 2>&1')) {
            $this->markTestSkipped('node is not on PATH.');
        }

        preg_match_all('#<script>(.*?)</script>#s', $html, $matches);
        $script = collect($matches[1])->first(fn ($s) => str_contains($s, 'window.ItemPicker'));
        $this->assertNotNull($script, 'The item picker script was not rendered.');

        $file = tempnam(sys_get_temp_dir(), 'picker') . '.js';
        file_put_contents($file, $script);
        exec('node --check ' . escapeshellarg($file) . ' 2>&1', $output, $status);
        @unlink($file);

        $this->assertSame(0, $status, 'Rendered item-picker script is not valid JS: ' . implode(' | ', $output));
    }

    /** A scan row as the model would have left it, without going through the upload. */
    private function scanRow(array $attributes): InvoiceScan
    {
        return InvoiceScan::create(array_merge([
            'user_id'           => $this->manager()->id,
            'file_path'         => 'invoice-scans/x.jpg',
            'original_filename' => 'x.jpg',
            'status'            => InvoiceScan::STATUS_SCANNED,
            'extracted'         => ['lines' => [['description' => 'Basil', 'quantity' => 1, 'unit_price' => 1]]],
        ], $attributes));
    }

    private function fakeReferenceLists(): void
    {
        Http::fake([
            '*/contacts*' => Http::response(['contacts' => [['id' => 2, 'name' => 'GREEN VALLEY FARM SDN BHD']]]),
            '*/accounts*' => Http::response(['accounts' => [['id' => 33, 'code' => '6508', 'name' => 'General Expense']]]),
            '*/products*' => Http::response(['products' => []]),
        ]);
    }

    /**
     * The push guard stops one scan becoming two bills; it cannot stop the same
     * paper being photographed twice, and each photo is its own scan with its
     * own Send button. The number is compared with case, spaces and punctuation
     * ignored, and when the twin is already a bill the flag says which one and
     * Send asks before making a second.
     */
    public function test_the_same_invoice_scanned_twice_is_flagged(): void
    {
        $this->fakeReferenceLists();

        $this->scanRow([
            'supplier_name' => 'GREEN VALLEY FARM SDN BHD', 'invoice_number' => 'INV-20001',
            'invoice_date' => '2026-08-28', 'total_amount' => 23,
            'status' => InvoiceScan::STATUS_POSTED, 'bukku_number' => 'BL-00039', 'posted_at' => now(),
        ]);
        $again = $this->scanRow([
            'supplier_name' => 'Green Valley Farm', 'invoice_number' => 'inv 20001',
            'invoice_date' => '2026-08-28', 'total_amount' => 23,
        ]);

        $this->actingAs($this->manager())
            ->get(route('invoice-scan.show', $again))
            ->assertOk()
            ->assertSee('Possible duplicate')
            ->assertSee('may already be in Bukku as BL-00039')
            ->assertSee('same invoice number')
            // The native confirm on Send, only because the twin is a bill.
            ->assertSee('Send it anyway and make a second bill?', false);

        $this->actingAs($this->manager())
            ->get(route('invoice-scan.index'))
            ->assertOk()
            ->assertSee('Duplicate?');
    }

    /**
     * The number is read by a model, so one misread character would slip past a
     * number-only check. Same supplier, same date and same total still catches it.
     */
    public function test_a_misread_number_is_still_caught_by_supplier_date_and_total(): void
    {
        $this->fakeReferenceLists();

        $this->scanRow([
            'supplier_name' => 'HILLSIDE AGROFARM', 'invoice_number' => 'INV-10432',
            'invoice_date' => '2026-09-01', 'total_amount' => '187.40',
        ]);
        $misread = $this->scanRow([
            'supplier_name' => 'Hillside Agrofarm.', 'invoice_number' => 'INV-1O432',
            'invoice_date' => '2026-09-01', 'total_amount' => 187.4,
        ]);

        $this->actingAs($this->manager())
            ->get(route('invoice-scan.show', $misread))
            ->assertOk()
            ->assertSee('Possible duplicate — this invoice looks like one scanned before.')
            ->assertSee('same supplier, date and total')
            // Neither is a bill yet, so there is nothing to confirm on Send.
            ->assertDontSee('Send it anyway', false);
    }

    public function test_different_invoices_from_one_supplier_are_not_flagged(): void
    {
        $this->fakeReferenceLists();

        $this->scanRow([
            'supplier_name' => 'HILLSIDE AGROFARM', 'invoice_number' => 'INV-10432',
            'invoice_date' => '2026-09-01', 'total_amount' => 187.40,
        ]);
        $next = $this->scanRow([
            'supplier_name' => 'HILLSIDE AGROFARM', 'invoice_number' => 'INV-10433',
            'invoice_date' => '2026-09-01', 'total_amount' => 92.10,
        ]);

        $this->actingAs($this->manager())
            ->get(route('invoice-scan.show', $next))
            ->assertOk()
            ->assertDontSee('Possible duplicate');

        $this->actingAs($this->manager())
            ->get(route('invoice-scan.index'))
            ->assertOk()
            ->assertDontSee('Duplicate?');
    }

    public function test_an_upload_is_read_and_stored_but_nothing_is_sent_to_bukku_yet(): void
    {
        $scan = $this->scannedRow();

        $this->assertSame('GREEN VALLEY FARM SDN BHD', $scan->supplier_name);
        $this->assertSame('INV-20001', $scan->invoice_number);
        $this->assertSame(InvoiceScan::STATUS_SCANNED, $scan->status);
        $this->assertCount(2, $scan->lines());
        Storage::assertExists($scan->file_path);

        // The whole safety story: reading an invoice must not touch the books.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'bukku'));
    }

    public function test_a_failed_read_still_leaves_a_row_to_work_from(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response('upstream exploded', 500)]);

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.store'), ['invoice' => UploadedFile::fake()->image('invoice.jpg')])
            ->assertRedirect();

        $scan = InvoiceScan::firstOrFail();

        $this->assertSame(InvoiceScan::STATUS_FAILED, $scan->status);
        Storage::assertExists($scan->file_path);
    }

    public function test_blank_lines_from_the_reader_are_dropped(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->fakeScanResponse([
            'lines' => [
                ['description' => 'Real line', 'quantity' => 1, 'unit_price' => 2, 'amount' => 2],
                ['description' => '', 'quantity' => null, 'unit_price' => null, 'amount' => null],
            ],
        ]))]);

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.store'), ['invoice' => UploadedFile::fake()->image('invoice.jpg')]);

        $this->assertCount(1, InvoiceScan::firstOrFail()->lines());
    }

    public function test_a_reviewed_scan_becomes_a_bill_and_the_reference_comes_back(): void
    {
        $scan = $this->scannedRow();

        Http::fake([
            '*/products/*'      => Http::response(['product' => self::PRODUCT_77]),
            '*/locations*'      => Http::response(['locations' => [['id' => 1, 'is_archived' => false]]]),
            '*/files'           => Http::response(['file' => ['id' => 73]]),
            '*/purchases/bills' => Http::response(['transaction' => [
                'id'         => 47,
                'number'     => 'BL-00039',
                'short_link' => 'https://yourcompany.bukku.my/l/example',
            ]]),
        ]);

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.push', $scan), [
                'contact_id'     => 2,
                'invoice_number' => 'INV-20001',
                'invoice_date'   => '2026-08-28',
                'term_id'        => 3,
                'lines'          => [
                    ['description' => 'Italian Sweet Basil 20g', 'quantity' => 5, 'unit_price' => 4, 'include' => '1', 'account_id' => 5, 'product_id' => 77],
                    ['description' => 'Delivery Fee', 'quantity' => 1, 'unit_price' => 3, 'include' => '1', 'account_id' => 33],
                ],
            ])
            ->assertRedirect();

        $scan->refresh();

        $this->assertSame(InvoiceScan::STATUS_POSTED, $scan->status);
        $this->assertSame(47, (int) $scan->bukku_transaction_id);
        $this->assertSame('BL-00039', $scan->bukku_number);
        // Normalised, because the suite runs on SQLite (which renders this
        // DECIMAL as "23") and production on MariaDB (which gives "23.00").
        $this->assertSame('23.00', number_format((float) $scan->total_amount, 2, '.', ''));

        // NET30 on an invoice dated the 28th falls due on 27 September.
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/purchases/bills')) {
                return false;
            }

            return $request['term_items'][0]['date'] === '2026-09-27'
                && $request['form_items'][0]['product_id'] === 77
                && $request['files'][0]['file_id'] === 73;
        });
    }

    public function test_the_same_invoice_cannot_be_billed_twice(): void
    {
        $scan = $this->scannedRow();
        $scan->update([
            'status'               => InvoiceScan::STATUS_POSTED,
            'bukku_transaction_id' => 47,
            'bukku_number'         => 'BL-00039',
        ]);

        Http::fake(['*' => Http::response(['transaction' => ['id' => 99]])]);

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.push', $scan), [
                'contact_id'   => 2,
                'invoice_date' => '2026-08-28',
                'term_id'      => 3,
                'lines'        => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1, 'include' => '1', 'account_id' => 33]],
            ])
            ->assertSessionHas('error');

        $this->assertSame(47, (int) $scan->fresh()->bukku_transaction_id);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/purchases/bills'));
    }

    public function test_a_bill_needs_a_supplier(): void
    {
        $scan = $this->scannedRow();

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.push', $scan), [
                'invoice_date' => '2026-08-28',
                'term_id'      => 3,
                'lines'        => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1, 'include' => '1', 'account_id' => 33]],
            ])
            ->assertSessionHasErrors('contact_id');

        $this->assertNull($scan->fresh()->bukku_transaction_id);
    }

    public function test_deleting_a_scan_never_pretends_to_undo_the_bill(): void
    {
        $scan = $this->scannedRow();
        $scan->update(['status' => InvoiceScan::STATUS_POSTED, 'bukku_number' => 'BL-00039']);

        $this->actingAs($this->manager())
            ->delete(route('invoice-scan.destroy', $scan))
            ->assertRedirect();

        $this->assertModelMissing($scan);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'bukku'));
    }

    /**
     * The one that keeps the books right.
     *
     * The reviewer maps a line to a stock product but leaves the row's account
     * on the General Expense default — as the screen ships it. The line must
     * still land on the product's own Inventory account, or the accounts say
     * the kitchen spent the money and bought nothing.
     */
    public function test_a_line_mapped_to_a_product_posts_against_that_products_own_account(): void
    {
        $scan = $this->scannedRow();

        Http::fake([
            '*/products/*'      => Http::response(['product' => self::PRODUCT_77]),
            '*/locations*'      => Http::response(['locations' => [['id' => 1, 'is_archived' => false]]]),
            '*/files'           => Http::response(['file' => ['id' => 73]]),
            '*/purchases/bills' => Http::response(['transaction' => ['id' => 48, 'number' => 'BL-00040']]),
        ]);

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.push', $scan), [
                'contact_id'   => 2,
                'invoice_date' => '2026-08-28',
                'term_id'      => 3,
                'lines'        => [
                    ['description' => 'Italian Sweet Basil 20g', 'quantity' => 5, 'unit_price' => 4, 'include' => '1', 'account_id' => 33, 'product_id' => 77],
                    ['description' => 'Delivery Fee', 'quantity' => 1, 'unit_price' => 3, 'include' => '1', 'account_id' => 33],
                ],
            ])
            ->assertSessionMissing('error')
            ->assertRedirect();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/purchases/bills')) {
                return false;
            }

            $stock = $request['form_items'][0];
            $other = $request['form_items'][1];

            return $stock['account_id'] === 5              // the product's, not the 33 that was posted
                && $stock['product_unit_id'] === 77
                && $stock['location_id'] === 1
                && $other['account_id'] === 33             // unmapped line keeps what the reviewer chose
                && ! isset($other['location_id']);
        });
    }

    public function test_a_bill_still_goes_out_when_the_product_lookup_fails(): void
    {
        $scan = $this->scannedRow();

        Http::fake([
            '*/products/*'      => Http::response([], 500),
            '*/locations*'      => Http::response([], 500),
            '*/files'           => Http::response(['file' => ['id' => 73]]),
            '*/purchases/bills' => Http::response(['transaction' => ['id' => 49, 'number' => 'BL-00041']]),
        ]);

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.push', $scan), [
                'contact_id'   => 2,
                'invoice_date' => '2026-08-28',
                'term_id'      => 3,
                'lines'        => [['description' => 'Basil', 'quantity' => 5, 'unit_price' => 4, 'include' => '1', 'account_id' => 33, 'product_id' => 77]],
            ])
            ->assertRedirect();

        $this->assertSame(InvoiceScan::STATUS_POSTED, $scan->fresh()->status);

        // Blunter than it should be, but a bill: the account the reviewer saw.
        Http::assertSent(fn ($request) => ! str_contains($request->url(), '/purchases/bills')
            || $request['form_items'][0]['account_id'] === 33);
    }

    /**
     * The unproven half of the payload, made harmless.
     *
     * `product_unit_id` and `location_id` were read off a bill's GET response,
     * never off an accepted request. If Bukku refuses them the bill must still
     * go — a manager holding an invoice they cannot file is the failure that
     * matters, not a slightly blunter line.
     */
    public function test_a_refused_payload_is_sent_again_without_the_unproven_fields(): void
    {
        $scan = $this->scannedRow();

        Http::fake([
            '*/products/*'      => Http::response(['product' => self::PRODUCT_77]),
            '*/locations*'      => Http::response(['locations' => [['id' => 1, 'is_archived' => false]]]),
            '*/files'           => Http::response(['file' => ['id' => 73]]),
            '*/purchases/bills' => Http::sequence()
                ->push(['message' => 'The given data was invalid.', 'errors' => ['form_items.0.location_id' => ['bad']]], 422)
                ->push(['transaction' => ['id' => 50, 'number' => 'BL-00042']]),
        ]);

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.push', $scan), [
                'contact_id'   => 2,
                'invoice_date' => '2026-08-28',
                'term_id'      => 3,
                'lines'        => [['description' => 'Basil', 'quantity' => 5, 'unit_price' => 4, 'include' => '1', 'account_id' => 33, 'product_id' => 77]],
            ])
            ->assertSessionMissing('error')
            ->assertRedirect();

        $scan->refresh();
        $this->assertSame(InvoiceScan::STATUS_POSTED, $scan->status);
        $this->assertSame('BL-00042', $scan->bukku_number);

        // Exactly two attempts: the 4xx must not be retried by the client, or
        // the second attempt would be an identical payload rather than the
        // plainer one.
        $attempts = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), '/purchases/bills'));

        $this->assertCount(2, $attempts);
        $this->assertSame(1, $attempts->first()[0]['form_items'][0]['location_id']);
        $this->assertArrayNotHasKey('location_id', $attempts->last()[0]['form_items'][0]);
        $this->assertArrayNotHasKey('product_unit_id', $attempts->last()[0]['form_items'][0]);
        // The account is not speculative and must survive the retry.
        $this->assertSame(5, $attempts->last()[0]['form_items'][0]['account_id']);
    }

    public function test_a_failed_write_that_actually_committed_is_adopted_not_repeated(): void
    {
        $scan = $this->scannedRow();

        // Bukku commits the bill, then the response fails. Without the lookup
        // this is exactly where one invoice becomes two.
        Http::fake([
            '*/products/*' => Http::response(['product' => self::PRODUCT_77]),
            '*/locations*' => Http::response(['locations' => [['id' => 1, 'is_archived' => false]]]),
            '*/files'      => Http::response(['file' => ['id' => 73]]),
            '*/purchases/bills*' => function ($request) {
                if ($request->method() === 'GET') {
                    return Http::response(['transactions' => [[
                        'id'          => 60,
                        'number'      => 'BL-00050',
                        'description' => 'Read from photo on the Inventory, Sales and Management System website (scan #1)',
                    ]]]);
                }

                return Http::response(['message' => 'Server Error'], 500);
            },
        ]);

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.push', $scan), [
                'contact_id'   => 2,
                'invoice_date' => '2026-08-28',
                'term_id'      => 3,
                'lines'        => [['description' => 'Basil', 'quantity' => 5, 'unit_price' => 4, 'include' => '1', 'account_id' => 33]],
            ])
            ->assertSessionMissing('error');

        $scan->refresh();
        $this->assertSame(InvoiceScan::STATUS_POSTED, $scan->status);
        $this->assertSame('BL-00050', $scan->bukku_number);

        // Exactly one POST. The recovery was a read, not a second write.
        $posts = collect(Http::recorded())
            ->filter(fn ($p) => $p[0]->method() === 'POST' && str_contains($p[0]->url(), '/purchases/bills'));
        $this->assertCount(1, $posts);
    }

    public function test_a_bill_that_never_landed_is_sent_once_more(): void
    {
        $scan = $this->scannedRow();

        Http::fake([
            '*/products/*' => Http::response(['product' => self::PRODUCT_77]),
            '*/locations*' => Http::response(['locations' => [['id' => 1, 'is_archived' => false]]]),
            '*/files'      => Http::response(['file' => ['id' => 73]]),
            '*/purchases/bills*' => function ($request) {
                static $posts = 0;

                if ($request->method() === 'GET') {
                    return Http::response(['transactions' => []]);   // it did not land
                }

                return ++$posts === 1
                    ? Http::response(['message' => 'Server Error'], 500)
                    : Http::response(['transaction' => ['id' => 61, 'number' => 'BL-00051']]);
            },
        ]);

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.push', $scan), [
                'contact_id'   => 2,
                'invoice_date' => '2026-08-28',
                'term_id'      => 3,
                'lines'        => [['description' => 'Basil', 'quantity' => 5, 'unit_price' => 4, 'include' => '1', 'account_id' => 33]],
            ])
            ->assertSessionMissing('error');

        $this->assertSame('BL-00051', $scan->fresh()->bukku_number);
    }

    /**
     * The search that finds the bill is a SUBSTRING match — "invoice #7"
     * returns #71 and #72 — so a near-miss must never be adopted as this
     * scan's bill.
     */
    public function test_a_similar_bill_is_not_mistaken_for_this_one(): void
    {
        $scan = $this->scannedRow();

        Http::fake([
            '*/products/*' => Http::response(['product' => self::PRODUCT_77]),
            '*/locations*' => Http::response(['locations' => [['id' => 1, 'is_archived' => false]]]),
            '*/files'      => Http::response(['file' => ['id' => 73]]),
            '*/purchases/bills*' => function ($request) {
                if ($request->method() === 'GET') {
                    return Http::response(['transactions' => [[
                        'id'          => 99,
                        'number'      => 'BL-00099',
                        // Someone else's bill, matched only because #1 is a
                        // substring of #12.
                        'description' => 'Read from photo on the Inventory, Sales and Management System website (scan #12)',
                    ]]]);
                }

                return Http::response(['message' => 'Server Error'], 500);
            },
        ]);

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.push', $scan), [
                'contact_id'   => 2,
                'invoice_date' => '2026-08-28',
                'term_id'      => 3,
                'lines'        => [['description' => 'Basil', 'quantity' => 5, 'unit_price' => 4, 'include' => '1', 'account_id' => 33]],
            ])
            ->assertSessionHas('error');

        $scan->refresh();
        $this->assertSame(InvoiceScan::STATUS_SCANNED, $scan->status);
        $this->assertNotSame('BL-00099', $scan->bukku_number);
    }

    /**
     * The review screen must survive having no Bukku credentials.
     *
     * `Bukku::http()` throws when the token is blank, and show() makes three
     * reads through it — so for every day the key was missing on production,
     * opening a scan served a 500 instead of the "nothing came back from
     * Bukku" banner this page was built to show. A read with no token now
     * degrades; a write still throws, because silently not filing a bill
     * would be far worse than an error.
     */
    public function test_the_review_screen_survives_bukku_being_unconfigured(): void
    {
        $scan = $this->scannedRow();

        config(['services.bukku.token' => null]);

        $this->actingAs($this->manager())
            ->get(route('invoice-scan.show', $scan))
            ->assertOk()
            ->assertSee('No suppliers came back from Bukku');

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'bukku'));
    }

    /**
     * The loop closing.
     *
     * The reviewer picks nothing but the shelf item — there is no account or
     * product box on the screen any more. The shelf item knows its Bukku
     * product, the product knows its own account, so a stock purchase posts
     * against Inventory rather than General Expense without anyone choosing it.
     */
    public function test_a_linked_shelf_item_puts_the_line_on_the_right_account_by_itself(): void
    {
        $scan = $this->scannedRow();

        $basil = \App\Models\InventoryItem::create([
            'name' => 'Italian Sweet Basil', 'category' => 'Vegetable', 'unit' => 'kg',
            'quantity_on_hand' => 0, 'unit_cost' => 4, 'bukku_product_id' => 77,
        ]);

        Http::fake([
            '*/products/77'     => Http::response(['product' => self::PRODUCT_77]),
            '*/locations*'      => Http::response(['locations' => [['id' => 1, 'is_archived' => false]]]),
            '*/contacts*'       => Http::response(['contacts' => [['id' => 2, 'name' => 'S']], 'paging' => ['total' => 1]]),
            '*/files'           => Http::response(['file' => ['id' => 73]]),
            '*/purchases/bills' => Http::response(['transaction' => ['id' => 55, 'number' => 'BL-00055']]),
        ]);

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.push', $scan), [
                'contact_id'   => 2,
                'invoice_date' => '2026-08-28',
                'term_id'      => 3,
                // No product_id and no account_id: the screen no longer asks.
                'lines'        => [[
                    'description' => 'Italian Sweet Basil 20g', 'quantity' => 5, 'unit_price' => 4,
                    'include' => '1', 'inventory_item_id' => $basil->id,
                ]],
            ])
            ->assertSessionMissing('error');

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST' || ! str_contains($request->url(), '/purchases/bills')) {
                return false;
            }

            $line = $request['form_items'][0];

            return $line['product_id'] === 77
                && $line['account_id'] === 5          // Inventory, off the product
                && $line['product_unit_id'] === 77
                && $line['location_id'] === 1;
        });
    }

    public function test_an_unlinked_shelf_item_still_falls_back_to_the_expense_account(): void
    {
        $scan = $this->scannedRow();

        $item = \App\Models\InventoryItem::create([
            'name' => 'Something Bukku does not track', 'category' => 'Vegetable', 'unit' => 'kg',
            'quantity_on_hand' => 0, 'unit_cost' => 1,
        ]);

        Http::fake([
            '*/contacts*'       => Http::response(['contacts' => [['id' => 2, 'name' => 'S']], 'paging' => ['total' => 1]]),
            '*/files'           => Http::response(['file' => ['id' => 73]]),
            '*/purchases/bills' => Http::response(['transaction' => ['id' => 56, 'number' => 'BL-00056']]),
        ]);

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.push', $scan), [
                'contact_id'   => 2,
                'invoice_date' => '2026-08-28',
                'term_id'      => 3,
                'lines'        => [['description' => 'X', 'quantity' => 1, 'unit_price' => 1, 'include' => '1', 'inventory_item_id' => $item->id]],
            ])
            ->assertSessionMissing('error');

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST' || ! str_contains($request->url(), '/purchases/bills')) {
                return false;
            }

            $line = $request['form_items'][0];

            return $line['account_id'] === 33 && ! isset($line['product_id']);
        });
    }
}
