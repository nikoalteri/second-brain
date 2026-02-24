<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('accounts')
            ->whereNull('balance')
            ->update(['balance' => 0]);

        DB::table('accounts')
            ->whereNull('is_debt')
            ->update(['is_debt' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data backfill is intentionally irreversible.
    }
};
