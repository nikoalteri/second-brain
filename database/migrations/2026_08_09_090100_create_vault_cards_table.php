<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lightweight vault-only entity for debit/prepaid cards: unlike CreditCard, it carries no
     * billing cycle/payment/interest machinery, since bancomat/prepagate aren't credit products.
     * account_id is nullable and optional — a prepaid card's own balance can be tracked via a
     * regular Account if the user wants that, but the card's sensitive data lives here regardless.
     * card_number/cvv/pin/security_code are ciphertext columns, same pattern as credit_cards
     * (see 2026_08_08_232037_add_vault_fields_to_credit_cards_table.php) — encrypted at rest AND
     * gated behind the vault-unlock session at the application layer.
     */
    public function up(): void
    {
        Schema::create('vault_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('brand');
            $table->text('card_number')->nullable();
            $table->unsignedTinyInteger('expiry_month')->nullable();
            $table->unsignedSmallInteger('expiry_year')->nullable();
            $table->text('cvv')->nullable();
            $table->text('pin')->nullable();
            $table->text('security_code')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('user_id');
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_cards');
    }
};
