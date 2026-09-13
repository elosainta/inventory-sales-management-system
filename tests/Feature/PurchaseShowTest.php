<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\MarketPurchase;
use App\Models\MarketPurchaseLine;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A purchase row opens its own detail page, the same way a scan opens its bill.
 */
class PurchaseShowTest extends TestCase
{
    use RefreshDatabase;

    private function purchase(): Purchase
    {
        $supplier = Supplier::create([
            'name' => 'HILLSIDE AGROFARM SDN BHD', 'contact' => '', 'email' => '', 'address' => '',
        ]);

        $purchase = Purchase::create([
            'supplier_id'    => $supplier->id,
            'invoice_number' => 'INV-2609/0001',
            'total_amount'   => 252.00,
            'status'         => 'completed',
            'purchase_date'  => now(),
        ]);

        $item = InventoryItem::create([
            'name' => 'Chicken Thigh', 'category' => 'Meat', 'unit' => 'kg',
            'quantity_on_hand' => 10, 'unit_cost' => 12.60, 'low_stock_level' => 2,
        ]);

        PurchaseLine::create([
            'purchase_id' => $purchase->id, 'inventory_item_id' => $item->id,
            'quantity' => 20, 'unit_price' => 12.60, 'line_total' => 252.00,
        ]);

        return $purchase;
    }

    public function test_the_list_links_each_row_to_its_purchase(): void
    {
        $purchase = $this->purchase();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))
            ->get(route('purchases.index', ['range' => 'year']))
            ->assertOk()
            ->assertSee(route('purchases.show', $purchase), escape: false);
    }

    public function test_the_detail_page_shows_the_lines(): void
    {
        $purchase = $this->purchase();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))
            ->get(route('purchases.show', $purchase))
            ->assertOk()
            ->assertSee('INV-2609/0001')
            ->assertSee('Chicken Thigh')
            ->assertSee('HILLSIDE AGROFARM SDN BHD');
    }

    public function test_a_junior_chef_cannot_open_a_purchase(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]))
            ->get(route('purchases.show', $this->purchase()))
            ->assertForbidden();
    }

    private function marketPurchase(): MarketPurchase
    {
        $purchase = MarketPurchase::create([
            'signed_by'     => 'Sam',
            'purchase_date' => now(),
            'total_amount'  => 30.00,
            'notes'         => 'Bought at the morning market.',
        ]);

        $item = InventoryItem::create([
            'name' => 'Spring Onion', 'category' => 'Vegetables', 'unit' => 'g',
            'quantity_on_hand' => 500, 'unit_cost' => 0.06, 'low_stock_level' => 100,
        ]);

        MarketPurchaseLine::create([
            'market_purchase_id' => $purchase->id, 'inventory_item_id' => $item->id,
            'quantity' => 500, 'unit_price' => 0.06, 'line_total' => 30.00,
        ]);

        return $purchase;
    }

    public function test_the_market_list_links_each_row_to_its_purchase(): void
    {
        $purchase = $this->marketPurchase();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))
            ->get(route('market-purchases.index', ['range' => 'year']))
            ->assertOk()
            ->assertSee(route('market-purchases.show', $purchase), escape: false);
    }

    public function test_the_market_detail_page_shows_the_lines(): void
    {
        $purchase = $this->marketPurchase();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))
            ->get(route('market-purchases.show', $purchase))
            ->assertOk()
            ->assertSee('Sam')
            ->assertSee('Spring Onion')
            ->assertSee('Bought at the morning market.');
    }

    public function test_a_junior_chef_cannot_open_a_market_purchase(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]))
            ->get(route('market-purchases.show', $this->marketPurchase()))
            ->assertForbidden();
    }
}
