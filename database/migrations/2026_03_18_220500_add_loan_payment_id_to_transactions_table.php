<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('loan_payment_id')
                ->nullable()
                ->after('to_account_id')
                ->constrained('loan_payments')
                ->nullOnDelete();

            $table->unique('loan_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['loan_payment_id']);
            $table->dropConstrainedForeignId('loan_payment_id');
        });
    }
};
