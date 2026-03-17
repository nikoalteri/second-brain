<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('monthly_payment', 10, 2);

            $table->unsignedTinyInteger('withdrawal_day');
            $table->boolean('skip_weekends')->default(true);

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->unsignedInteger('total_installments');
            $table->unsignedInteger('paid_installments')->default(0);

            $table->decimal('remaining_amount', 10, 2);
            $table->enum('status', ['active', 'completed', 'defaulted'])->default('active');

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
