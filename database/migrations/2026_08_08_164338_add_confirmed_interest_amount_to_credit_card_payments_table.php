<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('credit_card_payments', function (Blueprint $table) {
            $table->decimal('confirmed_interest_amount', 12, 2)->nullable()->after('interest_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_card_payments', function (Blueprint $table) {
            $table->dropColumn('confirmed_interest_amount');
        });
    }
};
