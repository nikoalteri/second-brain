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
        Schema::create('workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('type', ['cardio', 'strength', 'flexibility', 'sports', 'other'])->default('other');
            $table->integer('duration_minutes');
            $table->integer('calories_burned')->nullable();
            $table->string('exercise_name');
            $table->integer('distance_km')->nullable();
            $table->integer('intensity_level')->nullable(); // 1-10 scale
            $table->string('location')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workouts');
    }
};
