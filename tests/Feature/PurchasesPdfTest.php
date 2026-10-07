<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The purchases PDF is a month to a sheet and a line per supplier: what was
 * spent with each, and what is still owed. It used to be a supplier to a
 * sheet listing every ingredient, which on live ran to fourteen pages.
 */
class PurchasesPdfTest extends TestCase
{
    use RefreshDatabase;

    private function buy(string $date, string $supplier, string $qty, string $price, string $status = 'completed'): void
    {
        $purchase = Purchase::create([
            'supplier_id' => Supplier::firstOrCreate(
                ['name' => $supplier],
                ['contact' => '', 'email' => '', 'address' => '']
            )->id,
            'purchase_date' => $date,
            'status' => $status,
            'total_amount' => bcmul($qty, $price, 2),
            'user_id' => User::factory()->create()->id,
        ]);

        PurchaseLine::create([
            'purchase_id' => $purchase->id,
            'inventory_item_id' => InventoryItem::firstOrCreate(
                ['name' => 'CHUCK TENDER'],
                ['category' => 'Meat', 'unit' => 'kg', 'quantity_on_hand' => 0, 'reorder_threshold' => 0, 'unit_cost' => 0]
            )->id,
            'quantity' => $qty,
            'unit_price' => $price,
            'line_total' => bcmul($qty, $price, 2),
        ]);
    }

    private function pdf(): string
    {
        return $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get('/purchases/export/pdf')
            ->getContent();
    }

    private function pageCount(string $pdf): int
    {
        return preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
    }

    public function test_the_whole_report_is_one_page(): void
    {
        foreach (['2026-08-04', '2026-09-04', '2026-10-04'] as $date) {
            for ($i = 1; $i <= 4; $i++) {
                $this->buy($date, "SUPPLIER {$i}", '2', '10.00');
            }
        }

        $this->assertSame(1, $this->pageCount($this->pdf()));
    }

    /**
     * The type follows the row count so the sheet does not spill. The
     * thresholds are measured against real supplier names, which wrap and are
     * what sets the row height — a guess at them was wrong by ten rows.
     */
    public function test_a_long_report_shrinks_to_stay_on_one_page(): void
    {
        // 36 rows: past what 8pt holds, inside what 7pt does.
        foreach (range(1, 36) as $i) {
            $this->buy('2026-09-04', "NORTHGATE COLD STORAGE SDN. BHD. {$i}", '2', '10.00');
        }

        $this->assertSame(1, $this->pageCount($this->pdf()));
    }

    public function test_it_lists_suppliers_and_not_ingredients(): void
    {
        $this->buy('2026-09-02', 'NORTHGATE', '2', '40.00');

        $html = $this->render(['September 2026' => ['NORTHGATE' => ['count' => 1, 'total' => 80.0, 'paid' => 80.0, 'pending' => 0.0]]]);

        $this->assertStringContainsString('NORTHGATE', $html);
        $this->assertStringNotContainsString('CHUCK TENDER', $html, 'the ingredient detail is gone');
        $this->assertStringNotContainsString('Avg price', $html);
    }

    public function test_a_supplier_that_owes_nothing_shows_a_dash_not_a_zero(): void
    {
        $html = $this->render(['September 2026' => ['NORTHGATE' => ['count' => 1, 'total' => 80.0, 'paid' => 80.0, 'pending' => 0.0]]]);

        $this->assertStringContainsString('—', $html);
    }

    public function test_a_supplier_is_shown_with_what_it_is_still_owed(): void
    {
        $html = $this->render(['September 2026' => ['LOTUS' => ['count' => 2, 'total' => 150.0, 'paid' => 50.0, 'pending' => 100.0]]]);

        $this->assertStringContainsString('LOTUS', $html);
        $this->assertStringContainsString('RM 100.00', $html);
    }

    public function test_the_summary_splits_spent_into_paid_and_unpaid(): void
    {
        $html = $this->render([
            'September 2026' => [
                'NORTHGATE' => ['count' => 1, 'total' => 80.0, 'paid' => 80.0, 'pending' => 0.0],
                'LOTUS' => ['count' => 1, 'total' => 60.0, 'paid' => 0.0, 'pending' => 60.0],
            ],
        ]);

        $this->assertStringContainsString('RM 140.00', $html, 'spent');
        $this->assertStringContainsString('RM 80.00', $html, 'paid');
        $this->assertStringContainsString('RM 60.00', $html, 'unpaid');
    }

    public function test_months_read_oldest_first(): void
    {
        $html = $this->render([
            'August 2026'  => ['NORTHGATE' => ['count' => 1, 'total' => 10.0, 'paid' => 10.0, 'pending' => 0.0]],
            'October 2026' => ['NORTHGATE' => ['count' => 1, 'total' => 10.0, 'paid' => 10.0, 'pending' => 0.0]],
        ]);

        $this->assertLessThan(strpos($html, 'October 2026'), strpos($html, 'August 2026'));
    }

    public function test_a_junior_chef_cannot_export_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'junior_chef']))
            ->get('/purchases/export/pdf')
            ->assertForbidden();
    }

    /**
     * The PDF's own view, given the shape the controller builds.
     *
     * DomPDF compresses its content streams, so the words cannot be read back
     * out of the bytes — the page count is asserted against the PDF, the
     * figures against the view it is rendered from.
     */
    private function render(array $months): string
    {
        $shaped = collect($months)->map(fn ($suppliers, $label) => [
            'label'     => $label,
            'count'     => collect($suppliers)->sum('count'),
            'total'     => collect($suppliers)->sum('total'),
            'paid'      => collect($suppliers)->sum('paid'),
            'pending'   => collect($suppliers)->sum('pending'),
            'suppliers' => collect($suppliers),
        ]);

        return view('pdfs.purchases', [
            'months'  => $shaped,
            'summary' => [
                'count'   => $shaped->sum('count'),
                'total'   => $shaped->sum('total'),
                'paid'    => $shaped->sum('paid'),
                'pending' => $shaped->sum('pending'),
            ],
        ])->render();
    }
}
