<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * What is still owed is grouped by month and then by supplier. It used to be
 * one flat list of every supplier, which on live is 94 bills open at once.
 */
class OwedByMonthTest extends TestCase
{
    use RefreshDatabase;

    private function bill(array $overrides): array
    {
        return array_merge([
            'id' => random_int(1000, 99999),
            'number' => 'BL-00001',
            'number2' => null,
            'contact_id' => 1,
            'contact_name' => 'NORTHGATE',
            'status' => 'ready',
            'amount' => '100.00',
            'balance' => '100.00',
        ], $overrides);
    }

    private function owedPage(array $bills)
    {
        config([
            'services.bukku.token' => 'test-token',
            'services.bukku.url' => 'https://api.bukku.test',
            'services.anthropic.key' => 'test-key',
        ]);
        Cache::flush();

        Http::fake([
            '*/purchases/bills*' => Http::response(['transactions' => $bills, 'paging' => ['total' => count($bills)]]),
            '*' => Http::response([]),
        ]);

        return $this->actingAs(User::factory()->create(['role' => 'owner']))->get('/invoice-scan');
    }

    public function test_bills_are_grouped_by_month_with_the_oldest_month_first(): void
    {
        $response = $this->owedPage([
            $this->bill(['date' => '2026-08-05', 'balance' => '156.80']),
            $this->bill(['date' => '2026-10-06', 'balance' => '250.00']),
            $this->bill(['date' => '2026-09-19', 'balance' => '80.00']),
        ]);

        $response->assertOk();
        $response->assertSeeInOrder(['August 2026', 'September 2026', 'October 2026'], false);
    }

    public function test_a_month_carries_its_own_total_and_its_overdue_count(): void
    {
        // Two in one month, one of them well past thirty days.
        $response = $this->owedPage([
            $this->bill(['date' => today()->subDays(60)->toDateString(), 'balance' => '100.00']),
            $this->bill(['date' => today()->subDays(59)->toDateString(), 'balance' => '50.00']),
        ]);

        $response->assertSee('RM 150.00');
        $response->assertSee('2 over 30 days');
    }

    public function test_suppliers_are_counted_within_the_month_dearest_first(): void
    {
        $response = $this->owedPage([
            $this->bill(['date' => '2026-09-02', 'contact_id' => 1, 'contact_name' => 'NORTHGATE', 'balance' => '30.00']),
            $this->bill(['date' => '2026-09-11', 'contact_id' => 2, 'contact_name' => 'LOTUS', 'balance' => '400.00']),
        ]);

        $response->assertSee('2 suppliers');
        // The bigger balance leads, so the month reads worst-first.
        $response->assertSeeInOrder(['LOTUS', 'NORTHGATE'], false);
    }

    public function test_the_month_label_is_not_reparsed_from_the_grouping_key(): void
    {
        // Carbon fills a missing day from today, so parsing "2026-02" on the
        // 31st lands in March. The label must come off a row's own date.
        $this->travelTo('2026-03-31');

        $response = $this->owedPage([$this->bill(['date' => '2026-02-10', 'balance' => '42.00'])]);

        $response->assertSee('February 2026');
        $response->assertDontSee('March 2026');
    }
}
