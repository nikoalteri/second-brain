<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A second, independent secret specifically for revealing CVV/PIN — hashed like a password
     * (one-way), never decrypted/recoverable. Distinct from two_factor_secret: TOTP unlocks the
     * vault as a whole, this gates only the highest-risk fields within it.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('vault_pin')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('vault_pin');
        });
    }
};
