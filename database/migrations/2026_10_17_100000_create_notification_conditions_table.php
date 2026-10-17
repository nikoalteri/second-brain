<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('trigger', 40);
            $table->string('subject_type', 60);
            $table->unsignedBigInteger('subject_id');
            // Non-null on purpose so the unique key also covers open-ended conditions.
            $table->string('period_key', 32);
            $table->boolean('active')->default(true);
            $table->string('current_severity', 16)->nullable();
            $table->string('max_notified_severity', 16)->nullable();
            $table->unsignedInteger('episode')->default(1);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'trigger', 'subject_type', 'subject_id', 'period_key'],
                'notification_conditions_identity_unique'
            );
            $table->index(['user_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_conditions');
    }
};
