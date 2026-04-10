<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('value', 12, 2);
            $table->string('location');
            $table->date('purchase_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id']);
            $table->index(['property_id']);
            $table->index(['inventory_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
