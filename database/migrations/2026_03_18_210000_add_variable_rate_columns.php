<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->boolean('is_variable_rate')->default(false)->after('interest_rate');
        });

        Schema::table('loan_payments', function (Blueprint $table) {
            $table->decimal('interest_rate', 5, 4)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('is_variable_rate');
        });

        Schema::table('loan_payments', function (Blueprint $table) {
            $table->dropColumn('interest_rate');
        });
    }
};
