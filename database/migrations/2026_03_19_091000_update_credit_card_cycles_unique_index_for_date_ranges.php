<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_card_cycles', function (Blueprint $table) {
            $table->index('credit_card_id', 'credit_card_cycles_credit_card_id_idx');
            $table->dropUnique('credit_card_cycles_credit_card_id_period_month_unique');
            $table->unique(['credit_card_id', 'period_start_date', 'statement_date'], 'credit_card_cycles_card_period_range_unique');
        });
    }

    public function down(): void
    {
        Schema::table('credit_card_cycles', function (Blueprint $table) {
            $table->dropUnique('credit_card_cycles_card_period_range_unique');
            $table->unique(['credit_card_id', 'period_month']);
            $table->dropIndex('credit_card_cycles_credit_card_id_idx');
        });
    }
};
