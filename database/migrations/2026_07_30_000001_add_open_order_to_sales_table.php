<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // An "open order" is an extra order a staff member places with the
        // kitchen, usually at a discount. Deliberately no staff column — the
        // order is recorded anonymously; the audit log already records who
        // logged it if the Owner ever needs to trace one.
        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('is_open_order')->default(false)->after('recipe_id');
            $table->decimal('discount', 12, 2)->default(0)->after('selling_price');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['is_open_order', 'discount']);
        });
    }
};
