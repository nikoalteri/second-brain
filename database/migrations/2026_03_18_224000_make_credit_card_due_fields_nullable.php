<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->unsignedTinyInteger('due_day')->nullable()->change();
        });

        Schema::table('credit_card_cycles', function (Blueprint $table) {
            $table->date('due_date')->nullable()->change();
        });

        Schema::table('credit_card_payments', function (Blueprint $table) {
            $table->date('due_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->unsignedTinyInteger('due_day')->nullable(false)->change();
        });

        Schema::table('credit_card_cycles', function (Blueprint $table) {
            $table->date('due_date')->nullable(false)->change();
        });

        Schema::table('credit_card_payments', function (Blueprint $table) {
            $table->date('due_date')->nullable(false)->change();
        });
    }
};
