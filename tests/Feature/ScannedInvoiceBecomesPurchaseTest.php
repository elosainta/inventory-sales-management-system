<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InvoiceItemAlias;
use App\Models\InvoiceScan;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A filed invoice becomes this kitchen's own purchase.
 *
 * The delivery used to be typed twice — once for the accountant as a Bukku
 * bill, once for the kitchen as a Purchase. The tests that earn their place
 * here are the ones guarding the ways that can go wrong with real money and
 * real shelves: stock added exactly once, unmatched lines inventing nothing,
 * and a failure on this side never destroying a bill that is already filed.
 */
class ScannedInvoiceBecomesPurchaseTest extends TestCase
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

    private function item(string $name, float $onHand = 2, float $cost = 5): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name, 'category' => 'Meat', 'unit' => 'kg',
            'quantity_on_hand' => $onHand, 'unit_cost' => $cost,
        ]);
    }

    private function scan(): InvoiceScan
    {
        return InvoiceScan::create([
            'user_id'           => $this->manager()->id,
            'file_path'         => 'invoice-scans/x.jpg',
            'original_filename' => 'x.jpg',
            'status'            => InvoiceScan::STATUS_SCANNED,
            'supplier_name'     => 'HILLSIDE AGROFARM SDN BHD',
            'extracted'         => ['lines' => []],
        ]);
    }

    private function fakeBukku(): void
    {
        Http::fake([
            '*/contacts*'       => Http::response(['contacts' => [['id' => 7, 'legal_name' => 'HILLSIDE AGROFARM SDN BHD']], 'paging' => ['total' => 1]]),
            '*/accounts*'       => Http::response(['accounts' => [['id' => 33, 'name' => 'General Expense']]]),
            '*/products*'       => Http::response(['products' => [], 'paging' => ['total' => 0]]),
            '*/files'           => Http::response(['file' => ['id' => 1]]),
            '*/purchases/bills' => Http::response(['transaction' => ['id' => 91, 'number' => 'BL-00091']]),
        ]);
    }

    /** The kitchen's own supplier the reviewer picks; unlinked unless a test links it. */
    private function kitchenSupplier(string $name = 'HILLSIDE AGROFARM SDN BHD'): Supplier
    {
        return Supplier::firstOrCreate(['name' => $name], ['contact' => '', 'email' => '', 'address' => '']);
    }

    private function push(InvoiceScan $scan, array $lines, ?Supplier $supplier = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->manager())->post(route('invoice-scan.push', $scan), [
            'supplier_id'    => ($supplier ?? $this->kitchenSupplier())->id,
            'invoice_number' => 'KCS-1',
            'invoice_date'   => '2026-09-03',
            'term_id'        => 3,
            'lines'          => $lines,
        ]);
    }

    public function test_a_matched_line_adds_stock_and_records_a_purchase(): void
    {
        $chicken = $this->item('Chicken Thigh', onHand: 2, cost: 5);
        $this->fakeBukku();
        $scan = $this->scan();

        $this->push($scan, [[
            'description' => 'AYAM PEHA 1KG', 'quantity' => 3, 'unit_price' => 12.5,
            'account_id' => 33, 'include' => '1', 'inventory_item_id' => $chicken->id,
        ]])->assertRedirect();

        $purchase = Purchase::firstOrFail();

        $this->assertSame($purchase->id, $scan->fresh()->purchase_id);
        $this->assertSame('37.50', number_format((float) $purchase->total_amount, 2, '.', ''));

        // Stock in, and repriced at what was actually paid.
        $chicken->refresh();
        $this->assertSame('5.000', number_format((float) $chicken->quantity_on_hand, 3, '.', ''));
        $this->assertSame('12.50', number_format((float) $chicken->unit_cost, 2, '.', ''));
    }

    public function test_the_purchase_is_filed_under_the_supplier_picked_not_the_name_read_off_the_paper(): void
    {
        $chicken = $this->item('Chicken Thigh');
        $this->fakeBukku();
        $scan = $this->scan();
        $scan->update(['supplier_name' => 'B0H M1NG AGR0FARM']);

        // The model misread the letterhead. The reviewer picked the right
        // supplier anyway, and that pick is what the purchase must say.
        $this->actingAs($this->manager())->post(route('invoice-scan.push', $scan), [
            'supplier_id'   => $this->kitchenSupplier()->id,
            'invoice_date'  => '2026-09-03',
            'term_id'       => 3,
            'lines'         => [[
                'description' => 'AYAM PEHA 1KG', 'quantity' => 1, 'unit_price' => 10,
                'account_id' => 33, 'include' => '1', 'inventory_item_id' => $chicken->id,
            ]],
        ])->assertRedirect();

        $this->assertSame('HILLSIDE AGROFARM SDN BHD', Purchase::firstOrFail()->supplier->name);
        $this->assertSame(0, Supplier::where('name', 'B0H M1NG AGR0FARM')->count());
    }

    public function test_an_unmatched_line_invents_no_stock(): void
    {
        $this->fakeBukku();
        $scan = $this->scan();

        $this->push($scan, [[
            'description' => 'DELIVERY CHARGE', 'quantity' => 1, 'unit_price' => 5,
            'account_id' => 33, 'include' => '1',
        ]])->assertRedirect();

        // On the Bukku bill, and nowhere else. Guessing a shelf for it would
        // put fictional stock in front of the Owner.
        $this->assertSame(0, Purchase::count());
        $this->assertNull($scan->fresh()->purchase_id);
        $this->assertSame(0, InventoryItem::count());
    }

    public function test_the_supplier_is_reused_by_name_rather_than_duplicated(): void
    {
        $existing = Supplier::create(['name' => 'Hillside Agrofarm Sdn Bhd', 'contact' => '', 'email' => '', 'address' => '']);
        $chicken  = $this->item('Chicken Thigh');
        $this->fakeBukku();

        $this->push($this->scan(), [[
            'description' => 'AYAM', 'quantity' => 1, 'unit_price' => 9,
            'account_id' => 33, 'include' => '1', 'inventory_item_id' => $chicken->id,
        ]], $existing)->assertRedirect();

        // Different capitalisation, same company — the two lists were typed by
        // different people and must not fork into two suppliers.
        $this->assertSame(1, Supplier::count());
        $this->assertSame($existing->id, Purchase::firstOrFail()->supplier_id);

        // ...and is linked, so the next scan finds it by Bukku id, not by name,
        // without creating a second copy of it in Bukku either.
        $this->assertSame(7, (int) $existing->fresh()->bukku_contact_id);
        Http::assertNotSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/contacts'));
    }

    /**
     * The kitchen calls it "HILLSIDE"; Bukku spells it out. Matching by name
     * made a second supplier for every scan — the Suppliers page held two
     * RIVERSIDEs, two HARBOURs, three Green Valley Farms. The link is the join.
     */
    public function test_a_linked_supplier_is_used_whatever_bukku_calls_it(): void
    {
        $kitchen = Supplier::create(['name' => 'HILLSIDE', 'contact' => '', 'email' => '', 'address' => '', 'bukku_contact_id' => 7]);
        $chicken = $this->item('Chicken Thigh');
        $this->fakeBukku();
        $scan = $this->scan();

        $this->push($scan, [[
            'description' => 'AYAM', 'quantity' => 1, 'unit_price' => 9,
            'account_id' => 33, 'include' => '1', 'inventory_item_id' => $chicken->id,
        ]], $kitchen)->assertRedirect();

        $this->assertSame(1, Supplier::count());
        $this->assertSame($kitchen->id, Purchase::firstOrFail()->supplier_id);
        // The scan list reads the same as the Suppliers page.
        $this->assertSame('HILLSIDE', $scan->fresh()->supplier_name);
    }

    /**
     * The complaint that started this (2026-09-22): a supplier added on the
     * website could not be picked until someone also made it in Bukku. Now it
     * is picked from the Suppliers page and registered in Bukku on Send.
     */
    public function test_a_supplier_added_on_the_website_is_registered_in_bukku_on_send(): void
    {
        $new     = $this->kitchenSupplier('Hilltop Trading');
        $chicken = $this->item('Chicken Thigh');

        Http::fake(['*/contacts*' => fn ($request) => $request->method() === 'POST'
            ? Http::response(['contact' => ['id' => 30, 'legal_name' => 'Hilltop Trading']])
            : Http::response(['contacts' => [['id' => 7, 'legal_name' => 'HILLSIDE AGROFARM SDN BHD']], 'paging' => ['total' => 1]])]);
        $this->fakeBukku();

        $this->push($this->scan(), [[
            'description' => 'AYAM', 'quantity' => 1, 'unit_price' => 9,
            'account_id' => 33, 'include' => '1', 'inventory_item_id' => $chicken->id,
        ]], $new)->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(30, (int) $new->fresh()->bukku_contact_id);
        $this->assertCount(1, Http::recorded(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/contacts')));
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/contacts')
            && $r['legal_name'] === 'Hilltop Trading' && $r['types'] === ['supplier']
            // Required by Bukku's API; shaped like the codes Bukku makes itself.
            && preg_match('/^C-HILLTOPTR[0-9a-f]{4}$/', $r['contact_code']) === 1);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/purchases/bills') && $r['contact_id'] === 30);
        $this->assertSame($new->id, Purchase::firstOrFail()->supplier_id);
        $this->assertSame(1, Supplier::where('name', 'Hilltop Trading')->count());
    }

    public function test_a_supplier_bukku_refuses_to_create_sends_no_bill(): void
    {
        $new = $this->kitchenSupplier('Hilltop Trading');

        Http::fake(['*/contacts*' => fn ($request) => $request->method() === 'POST'
            ? Http::response(['message' => 'The legal name field is required.'], 422)
            : Http::response(['contacts' => [], 'paging' => ['total' => 0]])]);
        $this->fakeBukku();

        $this->push($this->scan(), [[
            'description' => 'AYAM', 'quantity' => 1, 'unit_price' => 9, 'account_id' => 33, 'include' => '1',
        ]], $new)->assertSessionHas('error');

        $this->assertNull($new->fresh()->bukku_contact_id);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/purchases/bills'));
    }

    /**
     * Two kitchen suppliers must not share one Bukku supplier, or purchases
     * split between them again - the duplicate the 1.31 merge cleaned up.
     */
    public function test_picking_a_duplicate_of_a_linked_supplier_is_refused(): void
    {
        $this->kitchenSupplier('HILLSIDE')->update(['bukku_contact_id' => 7]);
        $duplicate = $this->kitchenSupplier('HILLSIDE AGROFARM SDN BHD');
        $this->fakeBukku();

        $this->push($this->scan(), [[
            'description' => 'AYAM', 'quantity' => 1, 'unit_price' => 9, 'account_id' => 33, 'include' => '1',
        ]], $duplicate)->assertSessionHas('error', fn ($m) => str_contains($m, 'pick "HILLSIDE" instead'));

        $this->assertNull($duplicate->fresh()->bukku_contact_id);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/purchases/bills'));
    }

    public function test_a_refused_supplier_save_says_why(): void
    {
        $this->fakeBukku();

        $this->actingAs($this->manager())
            ->from(route('suppliers.index'))
            ->followingRedirects()
            ->post(route('suppliers.store'), ['name' => 'C.W.P TRANSPORTATION', 'contact' => str_repeat('1', 60), 'email' => 'a@b.co', 'address' => 'x'])
            ->assertSee('The supplier was not saved.');
    }

    public function test_two_suppliers_cannot_share_one_bukku_link(): void
    {
        Supplier::create(['name' => 'HILLSIDE', 'contact' => '', 'email' => '', 'address' => '', 'bukku_contact_id' => 7]);
        $other = Supplier::create(['name' => 'Hillside Farm', 'contact' => '1', 'email' => 'a@b.co', 'address' => 'x']);
        $this->fakeBukku();

        $this->actingAs($this->manager())
            ->patch(route('suppliers.update', $other), [
                'name' => 'Hillside Farm', 'contact' => '1', 'email' => 'a@b.co', 'address' => 'x', 'bukku_contact_id' => 7,
            ])
            ->assertSessionHasErrors('bukku_contact_id');

        $this->assertNull($other->fresh()->bukku_contact_id);
    }

    public function test_a_rejected_line_never_reaches_stock(): void
    {
        $chicken = $this->item('Chicken Thigh', onHand: 2);
        $this->fakeBukku();

        $this->push($this->scan(), [
            ['description' => 'AYAM PEHA', 'quantity' => 3, 'unit_price' => 12.5, 'account_id' => 33, 'include' => '1', 'inventory_item_id' => $chicken->id],
            ['description' => 'BAD READ',  'quantity' => 99, 'unit_price' => 99,  'account_id' => 33, 'include' => '0', 'inventory_item_id' => $chicken->id],
        ])->assertRedirect();

        // Only the ticked line moved the shelf: 2 + 3, not 2 + 3 + 99.
        $this->assertSame('5.000', number_format((float) $chicken->fresh()->quantity_on_hand, 3, '.', ''));
    }

    /**
     * The bill is already on the books by the time stock is touched, and
     * re-pushing is refused. So a failure here must report itself and leave
     * the filing intact — throwing away a successful bill because the shelf
     * update tripped would be the worse outcome by far.
     */
    public function test_a_failure_recording_stock_does_not_lose_the_filed_bill(): void
    {
        $chicken = $this->item('Chicken Thigh');
        $this->fakeBukku();
        $scan = $this->scan();

        $this->mock(\App\Domain\Purchasing\Actions\RecordPurchaseFromScan::class)
            ->shouldReceive('execute')->andThrow(new \RuntimeException('shelf exploded'));

        $this->push($scan, [[
            'description' => 'AYAM', 'quantity' => 1, 'unit_price' => 9,
            'account_id' => 33, 'include' => '1', 'inventory_item_id' => $chicken->id,
        ]])->assertRedirect();

        $scan->refresh();
        $this->assertSame(InvoiceScan::STATUS_POSTED, $scan->status);
        $this->assertSame('BL-00091', $scan->bukku_number);
        $this->assertNull($scan->purchase_id);
    }

    public function test_one_invoice_cannot_become_two_purchases(): void
    {
        $chicken = $this->item('Chicken Thigh', onHand: 2);
        $scan    = $this->scan();
        $scan->update(['purchase_id' => Purchase::create([
            'supplier_id' => Supplier::create(['name' => 'X', 'contact' => '', 'email' => '', 'address' => ''])->id,
            'total_amount' => 1, 'status' => 'completed', 'purchase_date' => now(),
        ])->id]);

        $made = app(\App\Domain\Purchasing\Actions\RecordPurchaseFromScan::class)->execute($scan, [
            'invoice_date' => '2026-09-03',
            'lines'        => [['quantity' => 5, 'unit_price' => 1, 'inventory_item_id' => $chicken->id]],
        ], 'X');

        $this->assertNull($made);
        $this->assertSame(1, Purchase::count());
        $this->assertSame('2.000', number_format((float) $chicken->fresh()->quantity_on_hand, 3, '.', ''));
    }

    public function test_the_dictionary_makes_the_next_invoice_move_stock_with_no_typing(): void
    {
        $chicken = $this->item('Chicken Thigh', onHand: 0);
        InvoiceItemAlias::remember('AYAM PEHA 1KG', $chicken->id);
        $this->fakeBukku();

        // Same wording, written differently — resolved without anyone matching
        // it again. This is the whole point of the feature.
        $matched = InvoiceItemAlias::matchAll(['Ayam Peha (1kg)']);
        $id      = $matched[InvoiceItemAlias::normalise('Ayam Peha (1kg)')] ?? null;

        $this->assertSame($chicken->id, $id);

        $this->push($this->scan(), [[
            'description' => 'Ayam Peha (1kg)', 'quantity' => 4, 'unit_price' => 11,
            'account_id' => 33, 'include' => '1', 'inventory_item_id' => $id,
        ]])->assertRedirect();

        $this->assertSame('4.000', number_format((float) $chicken->fresh()->quantity_on_hand, 3, '.', ''));
    }
}
