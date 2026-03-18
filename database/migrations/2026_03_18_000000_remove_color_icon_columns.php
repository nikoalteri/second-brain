<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['color', 'icon']);
        });

        Schema::table('transaction_types', function (Blueprint $table) {
            $table->dropColumn(['color', 'icon']);
        });

        Schema::table('transaction_categories', function (Blueprint $table) {
            $table->dropColumn(['color', 'icon']);
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('color')->nullable();
            $table->string('icon')->nullable();
        });

        Schema::table('transaction_types', function (Blueprint $table) {
            $table->string('color')->nullable();
            $table->string('icon')->nullable();
        });

        Schema::table('transaction_categories', function (Blueprint $table) {
            $table->string('color')->nullable();
            $table->string('icon')->nullable();
        });
    }
};
