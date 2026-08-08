<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saving_goal_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saving_goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('saving_goal_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saving_goal_contributions');
    }
};
