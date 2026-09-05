<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // The kitchen's second language is Bahasa Indonesia, not Malay, so the
    // stored locale moves from 'ms' to 'id' along with lang/id.json.
    public function up(): void
    {
        DB::table('users')->where('preferred_language', 'ms')->update(['preferred_language' => 'id']);
    }

    public function down(): void
    {
        DB::table('users')->where('preferred_language', 'id')->update(['preferred_language' => 'ms']);
    }
};
