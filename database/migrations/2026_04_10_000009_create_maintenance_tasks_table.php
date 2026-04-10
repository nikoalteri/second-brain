<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['preventive', 'repair', 'inspection', 'upgrade']);
            $table->enum('frequency', ['weekly', 'monthly', 'quarterly', 'annually', 'as_needed']);
            $table->text('description')->nullable();
            $table->date('last_completed_date')->nullable();
            $table->date('next_due_date')->nullable();
            $table->enum('status', ['active', 'paused', 'completed'])->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id']);
            $table->index(['property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_tasks');
    }
};
