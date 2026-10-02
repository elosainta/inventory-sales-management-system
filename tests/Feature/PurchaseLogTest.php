<?php

namespace Tests\Feature;

use App\Domain\Purchasing\Actions\LogPurchase;
use App\Models\InventoryItem;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Purchase log: one collapsible row per supplier, the same shape as the
 * Sales log is per day (the Owner, 2026-09-22).
 */
class PurchaseLogTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ROLE_OWNER]);
    }

    private function supplier(string $name): Supplier
    {
        return Supplier::create(['name' => $name, 'contact' => '', 'email' => '', 'address' => '']);
    }

    private function item(string $name, float $onHand = 0): InventoryItem
    {
        return InventoryItem::create(['name' => $name, 'category' => 'Meat', 'unit' => 'kg', 'quantity_on_hand' => $onHand, 'unit_cost' => 1]);
    }

    private function bought(Supplier $supplier, InventoryItem $item, float $price, string $date, string $status = 'completed'): Purchase
    {
        return app(LogPurchase::class)->execute([
            'supplier_id' => $supplier->id, 'status' => $status, 'purchase_date' => $date,
            'lines' => [['inventory_item_id' => $item->id, 'quantity' => 1, 'unit_price' => $price]],
        ]);
    }

    public function test_the_log_groups_purchases_by_supplier(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-22 12:00'));
        $hillside  = $this->supplier('HILLSIDE');
        $riverside = $this->supplier('RIVERSIDE');
        $kangkung = $this->item('Kangkung');

        $this->bought($hillside, $kangkung, 4.50, '2026-09-10');
        $this->bought($hillside, $kangkung, 5.00, '2026-09-12');
        $this->bought($riverside, $kangkung, 30.00, '2026-09-11');

        $this->actingAs($this->owner())
            ->get(route('purchases.index'))
            ->assertOk()
            // The Log a delivery sheet was tried and removed the same day.
            ->assertDontSee('Log a delivery')
            ->assertSeeText('2 suppliers — click a supplier to see its purchases.')
            ->assertSeeInOrder(['RIVERSIDE', '1 item · 1 purchase', 'RM 30.00', 'HILLSIDE', '2 items · 2 purchases', 'RM 9.50']);
    }

    public function test_the_month_can_be_searched_by_supplier(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-22 12:00'));
        $this->bought($this->supplier('HILLSIDE'), $this->item('Kangkung'), 4.50, '2026-09-10');

        $this->actingAs($this->owner())
            ->get(route('purchases.index'))
            ->assertSee('id="list-search"', false)
            ->assertSee('data-supplier="HILLSIDE"', false);

        // An empty month has nothing to search, so it gets no box.
        $this->get(route('purchases.index', ['month' => '2026-08']))
            ->assertOk()
            ->assertDontSee('id="list-search"', false);
    }

    public function test_one_photo_completes_a_suppliers_pending_purchases(): void
    {
        Storage::fake();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-22 12:00'));
        $lotus   = $this->supplier('LOTUS TRADING');
        $kangkung = $this->item('Kangkung');
        $first    = $this->bought($lotus, $kangkung, 12.40, '2026-09-10', 'pending');
        $second   = $this->bought($lotus, $kangkung, 8.00, '2026-09-11', 'pending');
        $done     = $this->bought($lotus, $kangkung, 3.00, '2026-09-12');
        $ids      = ['purchase_ids' => [$first->id, $second->id]];

        $this->actingAs(User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]))
            ->post(route('purchases.complete'), $ids + ['photo' => UploadedFile::fake()->image('statement.jpg')])
            ->assertForbidden();

        $this->actingAs($this->owner())
            ->get(route('purchases.index'))
            ->assertSeeText('2 pending');

        // Refused as a toast: the button sits in a dropdown that is collapsed after the reload.
        $this->post(route('purchases.complete'), $ids + ['photo' => UploadedFile::fake()->create('statement.pdf', 10, 'application/pdf')])
            ->assertSessionHas('error', 'That file is not a photo. Use JPG, PNG or WebP.');

        $this->post(route('purchases.complete'), $ids + ['photo' => UploadedFile::fake()->image('statement.jpg')])
            ->assertSessionHas('success', '2 purchases marked completed.');

        [$first, $second, $done] = [$first->fresh(), $second->fresh(), $done->fresh()];
        $this->assertSame(['completed', 'completed'], [$first->status, $second->status]);
        // One shared file, not a copy per purchase.
        $this->assertSame($first->receipt_path, $second->receipt_path);
        $this->assertCount(1, Storage::allFiles('receipts'));
        $this->assertNull($done->receipt_path);
    }

    public function test_a_shared_photo_stays_until_its_last_purchase_goes(): void
    {
        Storage::fake();
        $lotus   = $this->supplier('LOTUS TRADING');
        $kangkung = $this->item('Kangkung');
        $first    = $this->bought($lotus, $kangkung, 12.40, '2026-09-10', 'pending');
        $second   = $this->bought($lotus, $kangkung, 8.00, '2026-09-11', 'pending');

        $this->actingAs($this->owner())->post(route('purchases.complete'), [
            'purchase_ids' => [$first->id, $second->id],
            'photo'        => UploadedFile::fake()->image('statement.jpg'),
        ]);
        $photo = $first->fresh()->receipt_path;

        $this->delete(route('purchases.destroy', $first));
        Storage::assertExists($photo);

        $this->delete(route('purchases.destroy', $second));
        Storage::assertMissing($photo);
    }

}
