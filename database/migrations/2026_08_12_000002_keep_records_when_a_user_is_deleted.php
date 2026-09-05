<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Deleting a staff account used to fail outright (audits + purchases held a
    // RESTRICT foreign key) or quietly destroy kitchen records (sections,
    // market purchases, production batches and daily reports cascaded away with
    // the person). Both are wrong: the person goes, the records stay and simply
    // lose their owner.
    private const TABLES = ['audits', 'purchases', 'sections', 'market_purchases', 'production_batches', 'daily_reports'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['user_id']);
                $t->unsignedBigInteger('user_id')->nullable()->change();
                $t->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        // The audit log's whole point is attribution, so snapshot the name at
        // write time — otherwise a deleted person's history reads as "System".
        Schema::table('audits', fn (Blueprint $t) => $t->string('user_name')->nullable()->after('user_id'));

        // One statement per user rather than an UPDATE..JOIN: that syntax is
        // MySQL-only and fails on the sqlite test database. There are a handful
        // of users, so the loop costs nothing.
        foreach (DB::table('users')->select('id', 'name')->get() as $user) {
            DB::table('audits')->where('user_id', $user->id)->update(['user_name' => $user->name]);
        }
    }

    public function down(): void
    {
        Schema::table('audits', fn (Blueprint $t) => $t->dropColumn('user_name'));

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['user_id']);
                $t->foreign('user_id')->references('id')->on('users');
            });
        }
    }
};
