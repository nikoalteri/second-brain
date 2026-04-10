<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('utility_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('reading', 12, 2)->nullable();
            $table->decimal('cost', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id']);
            $table->index(['utility_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_bills');
    }
};
