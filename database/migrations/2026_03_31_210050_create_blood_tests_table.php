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
        Schema::create('blood_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('hemoglobin', 4, 1)->nullable(); // g/dL
            $table->decimal('hematocrit', 4, 1)->nullable(); // %
            $table->decimal('glucose', 6, 2)->nullable(); // mg/dL
            $table->decimal('cholesterol', 6, 2)->nullable(); // mg/dL
            $table->decimal('hdl', 6, 2)->nullable(); // mg/dL
            $table->decimal('ldl', 6, 2)->nullable(); // mg/dL
            $table->decimal('triglycerides', 6, 2)->nullable(); // mg/dL
            $table->integer('white_blood_cells')->nullable(); // K/uL
            $table->integer('red_blood_cells')->nullable(); // M/uL
            $table->integer('platelets')->nullable(); // K/uL
            $table->text('notes')->nullable();
            $table->string('lab_name')->nullable();
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
        Schema::dropIfExists('blood_tests');
    }
};
