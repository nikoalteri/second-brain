<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('cuisine', ['italian', 'asian', 'mexican', 'mediterranean', 'other'])->default('other');
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->integer('prep_time')->nullable(); // minutes
            $table->integer('cook_time')->nullable(); // minutes
            $table->integer('servings')->default(1);
            $table->json('ingredients_list')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'cuisine']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
