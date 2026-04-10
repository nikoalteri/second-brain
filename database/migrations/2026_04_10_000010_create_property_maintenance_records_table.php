<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_task_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('cost', 10, 2);
            $table->string('contractor')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id']);
            $table->index(['maintenance_task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_maintenance_records');
    }
};
