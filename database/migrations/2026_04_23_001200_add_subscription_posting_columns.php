<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('subscription_id')
                ->nullable()
                ->after('credit_card_payment_id')
                ->constrained()
                ->nullOnDelete();
            $table->date('subscription_renewal_date')
                ->nullable()
                ->after('date');
            $table->index(['subscription_id', 'subscription_renewal_date'], 'transactions_subscription_renewal_idx');
        });

        Schema::table('credit_card_expenses', function (Blueprint $table) {
            $table->foreignId('subscription_id')
                ->nullable()
                ->after('credit_card_cycle_id')
                ->constrained()
                ->nullOnDelete();
            $table->date('subscription_renewal_date')
                ->nullable()
                ->after('posted_at');
            $table->index(['subscription_id', 'subscription_renewal_date'], 'credit_card_expenses_subscription_renewal_idx');
        });
    }

    public function down(): void
    {
        Schema::table('credit_card_expenses', function (Blueprint $table) {
            $table->dropIndex('credit_card_expenses_subscription_renewal_idx');
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn('subscription_renewal_date');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_subscription_renewal_idx');
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn('subscription_renewal_date');
        });
    }
};
