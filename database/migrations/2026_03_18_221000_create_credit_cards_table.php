<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_cards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->enum('type', ['charge', 'revolving']);
            $table->decimal('credit_limit', 12, 2)->nullable();
            $table->decimal('fixed_payment', 12, 2)->nullable();
            $table->decimal('interest_rate', 7, 4)->nullable();
            $table->decimal('stamp_duty_amount', 10, 2)->default(2);

            $table->unsignedTinyInteger('statement_day');
            $table->unsignedTinyInteger('due_day');
            $table->boolean('skip_weekends')->default(true);

            $table->decimal('current_balance', 12, 2)->default(0);
            $table->enum('status', ['active', 'suspended', 'closed'])->default('active');

            $table->date('start_date')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['account_id']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_cards');
    }
};
