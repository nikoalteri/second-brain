<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recovery codes for the admin panel (/hub). Filament stores its recovery codes hashed, so
     * they cannot share `two_factor_recovery_codes`, which holds the plain codes the API checks.
     * The TOTP secret itself stays shared between the API and the panel.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('filament_recovery_codes')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('filament_recovery_codes');
        });
    }
};
