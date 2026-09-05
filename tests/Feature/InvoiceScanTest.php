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
                    'supplier_name'  => 'LITTLE FARMER SYNERGY SDN BHD',
                    'invoice_number' => 'JOT-5K0FGU',
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

    public function test_junior_chefs_and_part_timers_cannot_reach_it(): void
    {
        // Admin was on this list until 2026-09-03. The role now reaches every
        // feature but the dashboard, invoice scan included — posting a bill is
        // still gated behind a human pressing Send, which is the safety story
        // this screen actually rests on.
        foreach ([User::ROLE_JUNIOR_CHEF, User::ROLE_PART_TIMER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('invoice-scan.index'))
                ->assertForbidden();
        }
    }

    public function test_managers_can_reach_it(): void
    {
        foreach ([User::ROLE_OWNER, User::ROLE_HEAD_CHEF] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('invoice-scan.index'))
                ->assertOk()
                ->assertSee('BETA');
        }
    }

    public function test_the_review_screen_renders_with_what_was_read(): void
    {
        $scan = $this->scannedRow();

        Http::fake([
            '*/contacts*' => Http::response(['contacts' => [['id' => 2, 'name' => 'LITTLE FARMER SYNERGY SDN BHD']]]),
            '*/accounts*' => Http::response(['accounts' => [['id' => 33, 'code' => '6508', 'name' => 'General Expense']]]),
            '*/products*' => Http::response(['products' => [['id' => 77, 'name' => 'Italian Sweet Basil - Normal 20g']]]),
        ]);

        $this->actingAs($this->manager())
            ->get(route('invoice-scan.show', $scan))
            ->assertOk()
            ->assertSee('BETA')
            ->assertSee('LITTLE FARMER SYNERGY SDN BHD')
            ->assertSee('Italian Sweet Basil 20g')   // a line that was read
            ->assertSee('General Expense')           // the account picker
            ->assertSee('Send to Bukku');
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

    public function test_a_junior_chef_cannot_post_a_bill_even_with_a_scan_id(): void
    {
        $scan = $this->scannedRow();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]))
            ->post(route('invoice-scan.push', $scan), [
                'contact_id'   => 2,
                'invoice_date' => '2026-08-28',
                'term_id'      => 3,
                'lines'        => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1, 'account_id' => 33]],
            ])
            ->assertForbidden();

        $this->assertNull($scan->fresh()->bukku_transaction_id);
    }

    public function test_an_upload_is_read_and_stored_but_nothing_is_sent_to_bukku_yet(): void
    {
        $scan = $this->scannedRow();

        $this->assertSame('LITTLE FARMER SYNERGY SDN BHD', $scan->supplier_name);
        $this->assertSame('JOT-5K0FGU', $scan->invoice_number);
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
                'short_link' => 'https://yourcompany.bukku.my/l/dlB2yZvho-',
            ]]),
        ]);

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.push', $scan), [
                'contact_id'     => 2,
                'invoice_number' => 'JOT-5K0FGU',
                'invoice_date'   => '2026-08-28',
                'term_id'        => 3,
                'lines'          => [
                    ['description' => 'Italian Sweet Basil 20g', 'quantity' => 5, 'unit_price' => 4, 'account_id' => 5, 'product_id' => 77],
                    ['description' => 'Delivery Fee', 'quantity' => 1, 'unit_price' => 3, 'account_id' => 33],
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
                'lines'        => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1, 'account_id' => 33]],
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
                'lines'        => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1, 'account_id' => 33]],
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
                    ['description' => 'Italian Sweet Basil 20g', 'quantity' => 5, 'unit_price' => 4, 'account_id' => 33, 'product_id' => 77],
                    ['description' => 'Delivery Fee', 'quantity' => 1, 'unit_price' => 3, 'account_id' => 33],
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
                'lines'        => [['description' => 'Basil', 'quantity' => 5, 'unit_price' => 4, 'account_id' => 33, 'product_id' => 77]],
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
                'lines'        => [['description' => 'Basil', 'quantity' => 5, 'unit_price' => 4, 'account_id' => 33, 'product_id' => 77]],
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

    public function test_a_bukku_outage_is_not_retried_into_a_second_bill(): void
    {
        $scan = $this->scannedRow();

        Http::fake([
            '*/products/*'      => Http::response(['product' => self::PRODUCT_77]),
            '*/locations*'      => Http::response(['locations' => [['id' => 1, 'is_archived' => false]]]),
            '*/files'           => Http::response(['file' => ['id' => 73]]),
            '*/purchases/bills' => Http::response(['message' => 'Server Error'], 500),
        ]);

        $this->actingAs($this->manager())
            ->post(route('invoice-scan.push', $scan), [
                'contact_id'   => 2,
                'invoice_date' => '2026-08-28',
                'term_id'      => 3,
                'lines'        => [['description' => 'Basil', 'quantity' => 5, 'unit_price' => 4, 'account_id' => 33, 'product_id' => 77]],
            ])
            ->assertSessionHas('error');

        // A 500 might have committed, so it is never answered with a plainer
        // payload — that would be a second bill, not a second try.
        $this->assertSame(InvoiceScan::STATUS_SCANNED, $scan->fresh()->status);
    }
}
