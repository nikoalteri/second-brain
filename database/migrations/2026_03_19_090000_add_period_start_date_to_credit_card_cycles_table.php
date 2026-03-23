<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_card_cycles', function (Blueprint $table) {
            $table->date('period_start_date')->nullable()->after('period_month');
            $table->index('period_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('credit_card_cycles', function (Blueprint $table) {
            $table->dropIndex(['period_start_date']);
            $table->dropColumn('period_start_date');
        });
    }
};
