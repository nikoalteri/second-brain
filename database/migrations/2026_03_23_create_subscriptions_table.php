<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            $table->string('name');
            $table->decimal('monthly_cost', 10, 2)->nullable();
            $table->decimal('annual_cost', 10, 2)->nullable();
            $table->enum('frequency', ['monthly', 'annual', 'biennial'])->default('monthly');
            
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->date('next_renewal_date')->nullable();
            
            $table->foreignId('account_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('category_id')->nullable()->constrained('transaction_categories')->onDelete('set null');
            
            $table->boolean('auto_create_transaction')->default(false);
            $table->enum('status', ['active', 'inactive', 'cancelled'])->default('active');
            
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('user_id');
            $table->index('status');
            $table->index('next_renewal_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
