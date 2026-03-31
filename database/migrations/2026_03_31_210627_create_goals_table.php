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
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('category', ['health', 'career', 'finance', 'personal', 'relationship', 'other'])->default('other');
            $table->date('start_date');
            $table->date('target_date');
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'abandoned'])->default('not_started');
            $table->integer('progress_percentage')->default(0); // 0-100
            $table->decimal('target_value', 15, 2)->nullable(); // for quantifiable goals
            $table->decimal('current_value', 15, 2)->nullable();
            $table->string('unit')->nullable(); // e.g., kg, $, hours
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
