<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_budget_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->date('date');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id']);
            $table->index(['trip_participant_id']);
            $table->index(['trip_budget_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_expenses');
    }
};
