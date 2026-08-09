<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vault fields: encrypted at rest via the model's 'encrypted' casts, but ALSO gated behind
     * a short-lived MFA-verified vault session at the application layer (see VaultService /
     * EnsureVaultUnlocked middleware) — encryption alone isn't the control here, since the app
     * itself can always decrypt with APP_KEY. These columns hold ciphertext, hence `text`.
     */
    public function up(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->text('card_number')->nullable()->after('name');
            $table->unsignedTinyInteger('expiry_month')->nullable()->after('card_number');
            $table->unsignedSmallInteger('expiry_year')->nullable()->after('expiry_month');
            $table->text('cvv')->nullable()->after('expiry_year');
            $table->text('pin')->nullable()->after('cvv');
        });
    }

    public function down(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->dropColumn(['card_number', 'expiry_month', 'expiry_year', 'cvv', 'pin']);
        });
    }
};
