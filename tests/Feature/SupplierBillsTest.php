<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Every supplier bill, paid and unpaid, by month, with a PDF of the same.
 * Read-only: payments are recorded in Bukku, never here.
 */
class SupplierBillsTest extends TestCase
{
    use RefreshDatabase;

    /** A bill: amount is what it came to, balance is what is left on it. */
    private function bill(string $date, string $amount, string $balance, string $supplier = 'NORTHGATE'): array
    {
        return [
            'id' => random_int(1000, 99999),
            'number' => 'BL-' . random_int(10000, 99999),
            'number2' => 'INV-001',
            'contact_id' => crc32($supplier) % 1000,
            'contact_name' => $supplier,
            'status' => 'ready',
            'date' => $date,
            'amount' => $amount,
            'balance' => $balance,
        ];
    }

    private function fakeBukku(array $bills): void
    {
        config([
            'services.bukku.token' => 'test-token',
            'services.bukku.url' => 'https://api.bukku.test',
        ]);
        Cache::flush();

        Http::fake([
            '*/purchases/bills*' => Http::response(['transactions' => $bills, 'paging' => ['total' => count($bills)]]),
            '*' => Http::response([]),
        ]);
    }

    private function manager(string $role = 'owner'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function threeBills(): array
    {
        return [
            $this->bill('2026-08-05', '200.00', '200.00'),            // unpaid
            $this->bill('2026-08-20', '100.00', '0.00'),              // paid
            $this->bill('2026-09-02', '300.00', '120.00', 'LOTUS'), // part paid
        ];
    }

    public function test_unpaid_is_the_default_and_leaves_out_settled_bills(): void
    {
        $this->fakeBukku($this->threeBills());

        $response = $this->actingAs($this->manager())->get('/supplier-bills');

        $response->assertOk();
        // 200 still owed + 120 still owed; the settled 100 is not counted.
        $response->assertSee('RM 500.00');   // billed across the two
        $response->assertSee('RM 320.00');   // still owed
        $response->assertSee('Part paid');
    }

    public function test_paid_shows_only_bills_with_nothing_left_on_them(): void
    {
        $this->fakeBukku($this->threeBills());

        $response = $this->actingAs($this->manager())->get('/supplier-bills?status=paid');

        $response->assertOk();
        $response->assertSee('August 2026');
        $response->assertDontSee('September 2026');
        $response->assertDontSee('Part paid');
    }

    public function test_all_counts_every_bill_and_splits_billed_paid_and_owed(): void
    {
        $this->fakeBukku($this->threeBills());

        $response = $this->actingAs($this->manager())->get('/supplier-bills?status=all');

        $response->assertOk();
        $response->assertSee('RM 600.00');   // billed
        $response->assertSee('RM 280.00');   // paid: 0 + 100 + 180
        $response->assertSee('RM 320.00');   // still owed
    }

    public function test_months_read_oldest_first(): void
    {
        $this->fakeBukku($this->threeBills());

        $this->actingAs($this->manager())->get('/supplier-bills?status=all')
            ->assertSeeInOrder(['August 2026', 'September 2026'], false);
    }

    public function test_an_unknown_status_falls_back_to_unpaid_rather_than_showing_everything(): void
    {
        $this->fakeBukku($this->threeBills());

        // A money page must fail closed on a junk filter, not widen.
        $this->actingAs($this->manager())->get('/supplier-bills?status=../etc')
            ->assertOk()
            ->assertSee('RM 320.00');
    }

    public function test_a_head_chef_reaches_it_and_a_junior_chef_does_not(): void
    {
        $this->fakeBukku($this->threeBills());

        $this->actingAs($this->manager('head_chef'))->get('/supplier-bills')->assertOk();
        $this->actingAs($this->manager('junior_chef'))->get('/supplier-bills')->assertForbidden();
        $this->actingAs($this->manager('part_timer'))->get('/supplier-bills')->assertForbidden();
    }

    public function test_the_pdf_downloads_for_the_filter_that_was_on_screen(): void
    {
        $this->fakeBukku($this->threeBills());

        $response = $this->actingAs($this->manager())->get('/supplier-bills/export/pdf?status=all');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('supplier-bills-all-', $response->headers->get('content-disposition'));
    }

    public function test_a_bad_read_from_bukku_empties_the_page_rather_than_breaking_it(): void
    {
        config(['services.bukku.token' => 'test-token', 'services.bukku.url' => 'https://api.bukku.test']);
        Cache::flush();
        Http::fake(['*' => Http::response([], 500)]);

        // Bukku::cached() turns a bad read into an empty list by design, so
        // the page cannot tell that apart from a kitchen with no bills. It
        // says the honest thing instead of claiming an outage it cannot see.
        $this->actingAs($this->manager())->get('/supplier-bills')
            ->assertOk()
            ->assertSee('No unpaid bills to show.');
    }

    public function test_it_says_so_when_bukku_is_not_configured(): void
    {
        config(['services.bukku.token' => null, 'services.bukku.url' => null]);
        Cache::flush();
        Http::fake(['*' => Http::response([])]);

        $this->actingAs($this->manager())->get('/supplier-bills')
            ->assertOk()
            ->assertSee('Bukku is not configured on this server');
    }

    public function test_the_pdf_gives_each_month_its_own_sheet(): void
    {
        // Three months, one of them busy enough that it used to split across
        // two sheets at the house row height.
        $bills = [];
        foreach (['2026-08' => 30, '2026-09' => 4, '2026-10' => 3] as $month => $count) {
            for ($i = 1; $i <= $count; $i++) {
                $bills[] = $this->bill(
                    $month . '-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    '100.00',
                    '100.00',
                    'SUPPLIER ' . ($i % 6),
                );
            }
        }
        $this->fakeBukku($bills);

        $pdf = $this->actingAs($this->manager())->get('/supplier-bills/export/pdf?status=all')->getContent();

        // One overview sheet, then one per month.
        $this->assertSame(4, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf), 'a page per month plus the overview');
    }
}
