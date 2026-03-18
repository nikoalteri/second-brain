<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('interest_rate', 5, 2)->nullable()->change();
        });

        Schema::table('loan_payments', function (Blueprint $table) {
            $table->decimal('interest_rate', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('interest_rate', 5, 4)->nullable()->change();
        });

        Schema::table('loan_payments', function (Blueprint $table) {
            $table->decimal('interest_rate', 5, 4)->nullable()->change();
        });
    }
};
