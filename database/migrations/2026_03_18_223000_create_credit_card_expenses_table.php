<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_expenses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('credit_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_card_cycle_id')->nullable()->constrained()->nullOnDelete();

            $table->date('spent_at');
            $table->decimal('amount', 12, 2);
            $table->string('description');
            $table->string('notes')->nullable();

            $table->timestamps();

            $table->index(['credit_card_id', 'spent_at']);
            $table->index(['credit_card_cycle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_card_expenses');
    }
};
