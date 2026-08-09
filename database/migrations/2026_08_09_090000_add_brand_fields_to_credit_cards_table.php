<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `security_code` is Amex-only (their 4-digit CVV/CID has a distinct 3-digit "security code"
     * printed separately) — nullable and unused for Visa/Mastercard. Same ciphertext pattern as
     * card_number/cvv/pin (see 2026_08_08_232037_add_vault_fields_to_credit_cards_table.php).
     */
    public function up(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('type');
            $table->text('security_code')->nullable()->after('pin');
        });
    }

    public function down(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->dropColumn(['brand', 'security_code']);
        });
    }
};
