<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            // The unique indexes are the last line of defence: one ledger record can be the product
            // of at most one import row, however many confirmations race.
            $table->foreignId('transaction_id')->nullable()->unique()->after('flags')->constrained()->nullOnDelete();
            $table->foreignId('credit_card_expense_id')->nullable()->unique()->after('transaction_id')->constrained()->nullOnDelete();
            $table->string('failure_reason', 190)->nullable()->after('credit_card_expense_id');
        });
    }

    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaction_id');
            $table->dropConstrainedForeignId('credit_card_expense_id');
            $table->dropColumn('failure_reason');
        });
    }
};
