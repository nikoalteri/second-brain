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
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->text('content');
            $table->string('title')->nullable();
            $table->enum('mood', ['poor', 'fair', 'good', 'excellent'])->nullable();
            $table->enum('emotion', ['angry', 'sad', 'anxious', 'neutral', 'happy', 'excited', null])->nullable();
            $table->json('tags')->nullable(); // array of tags/keywords
            $table->boolean('is_private')->default(true);
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
        Schema::dropIfExists('journal_entries');
    }
};
