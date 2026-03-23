<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_cycles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('credit_card_id')->constrained()->cascadeOnDelete();

            $table->char('period_month', 7);
            $table->date('statement_date');
            $table->date('due_date');

            $table->decimal('total_spent', 12, 2)->default(0);
            $table->decimal('interest_amount', 12, 2)->default(0);
            $table->decimal('principal_amount', 12, 2)->default(0);
            $table->decimal('stamp_duty_amount', 12, 2)->default(0);
            $table->decimal('total_due', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);

            $table->enum('status', ['open', 'issued', 'paid', 'overdue'])->default('open');
            $table->timestamps();

            $table->unique(['credit_card_id', 'period_month']);
            $table->index(['status']);
            $table->index(['statement_date']);
            $table->index(['due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_card_cycles');
    }
};
