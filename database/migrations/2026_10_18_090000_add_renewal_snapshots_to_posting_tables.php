<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy rows stay NULL on purpose: nothing proves which frequency or currency they were
     * posted with, so they are never treated as comparable by price-hike detection.
     */
    public function up(): void
    {
        foreach (['transactions', 'credit_card_expenses'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('renewal_frequency', 50)->nullable()->after('subscription_renewal_date');
                $table->string('renewal_currency', 3)->nullable()->after('renewal_frequency');
            });
        }
    }

    public function down(): void
    {
        foreach (['transactions', 'credit_card_expenses'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['renewal_frequency', 'renewal_currency']);
            });
        }
    }
};
