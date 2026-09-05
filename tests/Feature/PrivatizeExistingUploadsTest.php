<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivatizeExistingUploadsTest extends TestCase
{
    use RefreshDatabase;

    private function makePurchase(string $receiptPath): Purchase
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'S', 'contact' => 'c', 'email' => 'e@e.com', 'address' => 'a']);

        return Purchase::create([
            'supplier_id' => $supplier->id, 'user_id' => $user->id,
            'total_amount' => 10, 'status' => 'completed', 'purchase_date' => now(),
            'receipt_path' => $receiptPath,
        ]);
    }

    public function test_it_moves_a_public_receipt_to_the_private_disk_and_removes_the_public_copy(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        Storage::disk('public')->put('receipts/old.jpg', 'fake-image-bytes');
        $purchase = $this->makePurchase('receipts/old.jpg');

        $this->artisan('uploads:privatize')->assertSuccessful();

        Storage::disk('local')->assertExists('receipts/old.jpg');
        Storage::disk('public')->assertMissing('receipts/old.jpg');
        // The DB column is untouched - the same relative path works on either disk.
        $this->assertSame('receipts/old.jpg', $purchase->fresh()->receipt_path);
    }

    public function test_dry_run_moves_nothing(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        Storage::disk('public')->put('receipts/old.jpg', 'fake-image-bytes');
        $this->makePurchase('receipts/old.jpg');

        $this->artisan('uploads:privatize --dry-run')->assertSuccessful();

        Storage::disk('public')->assertExists('receipts/old.jpg');
        Storage::disk('local')->assertMissing('receipts/old.jpg');
    }

    public function test_running_it_twice_is_a_no_op_the_second_time(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        Storage::disk('public')->put('receipts/old.jpg', 'fake-image-bytes');
        $this->makePurchase('receipts/old.jpg');

        $this->artisan('uploads:privatize')->assertSuccessful();
        $this->artisan('uploads:privatize')->assertSuccessful();

        Storage::disk('local')->assertExists('receipts/old.jpg');
    }

    public function test_a_missing_file_is_reported_not_crashed_on(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->makePurchase('receipts/never-existed.jpg');

        $this->artisan('uploads:privatize')->assertSuccessful();
    }
}
