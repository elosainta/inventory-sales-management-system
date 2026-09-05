<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            // Human-friendly unique reference, e.g. FK-00001.
            $table->string('ticket_number')->nullable()->unique()->after('id');
            // Developer-only triage tag: feature | bug | other | null (untagged).
            // NOT exposed on the user-facing /support form.
            $table->string('type')->nullable()->after('status');
        });

        // Backfill existing tickets with FK-xxxxx based on their id. Done in PHP
        // rather than one CONCAT/LPAD statement: those are MySQL-only and broke
        // every test run against the sqlite test database. This table holds
        // support tickets, so the row-at-a-time cost is irrelevant.
        foreach (DB::table('support_tickets')->whereNull('ticket_number')->pluck('id') as $id) {
            DB::table('support_tickets')->where('id', $id)
                ->update(['ticket_number' => sprintf('FK-%05d', $id)]);
        }
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropUnique(['ticket_number']);
            $table->dropColumn(['ticket_number', 'type']);
        });
    }
};
