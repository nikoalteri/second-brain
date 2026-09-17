<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->decimal('opening_balance', 12, 2)
                ->default(0)
                ->after('current_balance')
                ->comment('Starting debt not backed by a tracked CreditCardExpense. syncCardBalance() computes current_balance = opening_balance + expenses - paid principal.');
        });
    }

    public function down(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->dropColumn('opening_balance');
        });
    }
};
