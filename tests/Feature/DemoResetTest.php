<?php

namespace Tests\Feature;

use App\Console\Commands\DemoReset;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The sandbox has its own database but shares the storage volume with live,
 * so an upload path cloned across names the real file. Until 2026-10-02
 * purchases and market purchases crossed with their receipt paths, and a demo
 * Head Chef deleting one ran Storage::delete() on the live receipt.
 *
 * demo:reset itself speaks MariaDB (SHOW TABLES, SHOW CREATE TABLE), so this
 * checks the two halves that run anywhere: the lists against the real schema,
 * and the row copy.
 */
class DemoResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_upload_pointer_on_a_cloned_table_is_cleared(): void
    {
        $pointers = $this->clonedUploadPointers();
        $cleared  = collect(DemoReset::CLEAR_UPLOADS)
            ->map(fn (string $column, string $table) => "{$table}.{$column}")
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing($cleared, array_keys($pointers), 'Every upload column on a cloned table belongs in DemoReset::CLEAR_UPLOADS, or its table in SKIP_DATA — storage is shared with live.');
        $this->assertNotContains(false, $pointers, 'A NOT NULL upload column cannot be cleared on the way across — name its table in SKIP_DATA instead.');
    }

    public function test_a_cloned_purchase_arrives_without_its_receipt(): void
    {
        Purchase::create([
            'supplier_id'  => Supplier::create(['name' => 'Lotus', 'contact' => '', 'email' => '', 'address' => ''])->id,
            'user_id'      => User::factory()->create()->id,
            'receipt_path' => 'receipts/live.jpg',
        ]);

        config(['database.connections.sandbox' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        $sandbox = DB::connection('sandbox');
        $sandbox->statement(DB::table('sqlite_master')->where('name', 'purchases')->value('sql'));

        (new ReflectionMethod(DemoReset::class, 'copyRows'))->invoke(new DemoReset, DB::connection(), $sandbox, 'purchases');

        $this->assertSame(1, $sandbox->table('purchases')->count());
        $this->assertNull($sandbox->table('purchases')->value('receipt_path'));
        $this->assertSame('receipts/live.jpg', Purchase::value('receipt_path'));
    }

    /**
     * "table.column" => nullable, for every upload column on a table whose rows
     * are cloned.
     *
     * ponytail: found by name — every upload site today stores into a `path`
     * or `*_path` column. One named otherwise (`image`, `attachment`) slips
     * past; widen the pattern when a column like that is added.
     */
    private function clonedUploadPointers(): array
    {
        return collect(Schema::getTableListing(schemaQualified: false))
            ->diff(DemoReset::SKIP_DATA)
            ->flatMap(fn (string $table) => collect(Schema::getColumns($table))
                ->filter(fn (array $column) => preg_match('/(^|_)path$/', $column['name']))
                ->mapWithKeys(fn (array $column) => ["{$table}.{$column['name']}" => $column['nullable']]))
            ->all();
    }
}
