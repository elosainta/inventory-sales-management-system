<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Owner lands on the dashboard, so what is owed to suppliers has to be
 * there too - read off Bukku, and hidden rather than "RM 0" when Bukku
 * cannot be read.
 */
class DashboardOwedToSuppliersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.bukku.token' => 'test-token']);
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ROLE_OWNER]);
    }

    public function test_the_owner_sees_what_is_owed_and_where_to_find_the_bills(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-19'));

        Http::fake(['*/purchases/bills*' => Http::response(['transactions' => [
            ['id' => 1, 'number' => 'BL-1', 'contact_id' => 9, 'date' => '2026-08-01', 'amount' => 100, 'balance' => 100, 'status' => 'ready'],
            ['id' => 2, 'number' => 'BL-2', 'contact_id' => 9, 'date' => '2026-09-10', 'amount' => 50.5, 'balance' => 20.5, 'status' => 'ready'],
            ['id' => 3, 'number' => 'BL-3', 'contact_id' => 9, 'date' => '2026-09-11', 'amount' => 80, 'balance' => 0, 'status' => 'ready'],
            ['id' => 4, 'number' => 'BL-4', 'contact_id' => 9, 'date' => '2026-07-01', 'amount' => 999, 'balance' => 999, 'status' => 'void'],
        ], 'paging' => ['total' => 4]])]);

        $this->actingAs($this->owner())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Owed to suppliers')
            ->assertSee('RM 120.50')
            ->assertSee('2 unpaid bills in Bukku')
            ->assertSee('oldest 49 days')
            ->assertSee(route('invoice-scan.index') . '#owed', false);
    }

    public function test_nothing_is_claimed_when_bukku_cannot_be_read(): void
    {
        Http::fake(['*/purchases/bills*' => Http::response('down', 500)]);

        $this->actingAs($this->owner())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Owed to suppliers');
    }
}
