<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('subject');
            $table->text('content');
            $table->timestamp('read_at')->nullable();
            $table->enum('importance', ['low', 'medium', 'high'])->default('medium');
            $table->enum('category', ['personal', 'work', 'urgent'])->default('personal');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'importance']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
