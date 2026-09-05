<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['purchases', 'sales', 'wastage_entries', 'float_issuances'] as $table) {
            if (Schema::hasColumn($table, 'staff_id')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    // Was a raw `SHOW KEYS`, which is MySQL-only and so failed
                    // every test run against the sqlite test database. Laravel's
                    // own introspection answers the same question on any driver.
                    $hasForeignKey = collect(Schema::getForeignKeys($table))
                        ->contains(fn ($key) => in_array('staff_id', $key['columns']));
                    if ($hasForeignKey) {
                        $t->dropForeign($table . '_staff_id_foreign');
                    }
                    $t->dropColumn('staff_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['purchases', 'sales', 'wastage_entries', 'float_issuances'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('staff_id')->nullable();
            });
        }
    }
};
