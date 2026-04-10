<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['electricity', 'gas', 'water', 'internet', 'phone', 'waste']);
            $table->string('provider');
            $table->string('account_number')->nullable();
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'annual']);
            $table->integer('billing_day')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id']);
            $table->index(['property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utilities');
    }
};
