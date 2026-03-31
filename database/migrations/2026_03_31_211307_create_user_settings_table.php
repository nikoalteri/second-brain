<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('setting_key', ['theme', 'language', 'notifications', 'privacy'])->default('theme');
            $table->string('setting_value');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'setting_key']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
