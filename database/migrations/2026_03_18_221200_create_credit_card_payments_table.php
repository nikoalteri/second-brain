<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('credit_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_card_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();

            $table->date('due_date');
            $table->date('actual_date')->nullable();

            $table->decimal('installment_amount', 12, 2);
            $table->decimal('interest_amount', 12, 2)->default(0);
            $table->decimal('principal_amount', 12, 2)->default(0);
            $table->decimal('stamp_duty_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);

            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->string('notes')->nullable();

            $table->timestamps();

            $table->index(['credit_card_id', 'status']);
            $table->index(['due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_card_payments');
    }
};
