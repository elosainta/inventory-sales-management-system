<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('float_issuances', function (Blueprint $table) {
            $table->string('receipt_path')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('float_issuances', function (Blueprint $table) {
            $table->dropColumn('receipt_path');
        });
    }
};