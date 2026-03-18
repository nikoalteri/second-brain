<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $cols = collect(['color', 'icon'])->filter(
                fn($c) => Schema::hasColumn('accounts', $c)
            )->toArray();
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('transaction_types', function (Blueprint $table) {
            $cols = collect(['color', 'icon'])->filter(
                fn($c) => Schema::hasColumn('transaction_types', $c)
            )->toArray();
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('transaction_categories', function (Blueprint $table) {
            $cols = collect(['color', 'icon'])->filter(
                fn($c) => Schema::hasColumn('transaction_categories', $c)
            )->toArray();
            if ($cols) {
                $table->dropColumn($cols);
            }
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
