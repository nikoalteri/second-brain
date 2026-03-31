<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('destination');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('trip_type', ['vacation', 'business', 'adventure'])->default('vacation');
            $table->enum('status', ['planned', 'in_progress', 'completed'])->default('planned');
            $table->decimal('budget', 10, 2)->nullable();
            $table->decimal('total_spent', 10, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
