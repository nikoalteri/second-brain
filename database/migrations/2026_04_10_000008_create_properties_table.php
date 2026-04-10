<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('address', 255);
            $table->enum('property_type', ['house', 'apartment', 'condo', 'commercial']);
            $table->date('lease_start_date')->nullable();
            $table->date('lease_end_date')->nullable();
            $table->decimal('estimated_value', 12, 2);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
