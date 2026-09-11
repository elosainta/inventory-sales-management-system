<?php

namespace Tests\Feature;

use App\Models\MarketPurchase;
use App\Models\Purchase;
use App\Models\InventoryItem;
use App\Models\Section;
use App\Models\SectionCheck;
use App\Models\SectionTask;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Receipts and check photos moved off the public disk (they can show a card
 * number or a person's face) onto the private disk, served through routes
 * that check a Gate instead of a guessable /storage/... URL.
 */
class PrivateUploadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_receipt_is_stored_privately_and_not_public(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $supplier = Supplier::create(['name' => 'Test Supplier', 'contact' => '012-3456789', 'email' => 'supplier@example.com', 'address' => 'Malaysia']);
        $item = InventoryItem::create(['name' => 'Salt', 'category' => 'Pantry', 'unit' => 'kg', 'quantity_on_hand' => 5, 'unit_cost' => 2]);

        $this->actingAs($owner)->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'purchase_date' => now()->format('Y-m-d'),
            'lines' => [['inventory_item_id' => $item->id, 'quantity' => 1, 'unit_price' => 2]],
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $purchase = Purchase::firstOrFail();
        Storage::disk('local')->assertExists($purchase->receipt_path);
        Storage::disk('public')->assertMissing($purchase->receipt_path);

        $this->actingAs($owner)->get(route('purchases.receipt', $purchase))->assertOk();

        $junior = User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]);
        $this->actingAs($junior)->get(route('purchases.receipt', $purchase))->assertForbidden();
    }

    public function test_market_purchase_receipt_is_gated(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $marketPurchase = MarketPurchase::create([
            'user_id' => $owner->id,
            'purchase_date' => now(),
            'total_amount' => 10,
            'signed_by' => 'Test Owner',
            'receipt_path' => UploadedFile::fake()->image('r.jpg')->store('market-receipts'),
        ]);

        $this->actingAs($owner)->get(route('market-purchases.receipt', $marketPurchase))->assertOk();

        $junior = User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]);
        $this->actingAs($junior)->get(route('market-purchases.receipt', $marketPurchase))->assertForbidden();
    }

    /**
     * Check photos stopped being private-to-the-uploader in 1.10.49. A check is
     * the section's record of work done, not a personal file: everyone in the
     * kitchen works the same list and can see the proof against it. The line
     * that still holds is Admin — a support operator has no kitchen access.
     */
    public function test_anyone_signed_in_can_see_a_task_check_photo(): void
    {
        Storage::fake('local');

        $section = Section::create(['name' => 'Hot Kitchen']);
        $task = SectionTask::create(['section_id' => $section->id, 'title' => 'Clean grill', 'sort_order' => 0]);

        $chefA = User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]);

        $check = SectionCheck::create([
            'section_task_id' => $task->id,
            'user_id'         => $chefA->id,
            'checked_date'    => today(),
            'photo_path'      => UploadedFile::fake()->image('t.jpg')->store('task-checks'),
        ]);

        // The chef who uploaded it, a colleague who did not, and both managers.
        foreach ([$chefA->role, User::ROLE_JUNIOR_CHEF, User::ROLE_HEAD_CHEF, User::ROLE_OWNER] as $role) {
            $viewer = User::factory()->create(['role' => $role]);
            $this->actingAs($viewer)->get(route('prep.task-check.photo', $check))->assertOk();
        }
        $this->actingAs($chefA)->get(route('prep.task-check.photo', $check))->assertOk();

        // Admin was excluded until 1.11.2, when the support operator was given
        // the operational screens. These are proof-of-work photos of chillers
        // and switched-off equipment — already visible to the whole kitchen,
        // and carrying no money or personal data. The route is still gated
        // (`view-checklist`) rather than public, and a signed-out request is
        // covered by the guest test above.
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('prep.task-check.photo', $check))->assertOk();
    }
}
