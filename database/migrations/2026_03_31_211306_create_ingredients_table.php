<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('unit', ['g', 'ml', 'tbsp', 'cup', 'piece'])->default('piece');
            $table->enum('category', ['vegetable', 'meat', 'grain', 'dairy', 'spice', 'other'])->default('other');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
