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
        Schema::create('trip_itinerary_conflicts', function (Blueprint $table) {
            $table->id();
            
            // User scoping
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Trip reference
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            
            // Itinerary reference
            $table->foreignId('itinerary_id')->constrained()->cascadeOnDelete();
            
            // Activity references for conflicting activities
            $table->unsignedBigInteger('activity_1_id');
            $table->unsignedBigInteger('activity_2_id');
            
            // Conflict time range
            $table->dateTime('conflict_start');
            $table->dateTime('conflict_end');
            
            // Resolution tracking
            $table->dateTime('resolved_at')
                ->nullable()
                ->index();
            
            // User notes about the conflict
            $table->text('notes')
                ->nullable();
            
            // Soft deletes for archive purposes
            $table->softDeletes();
            
            $table->timestamps();

            // Add foreign keys for activities (using raw syntax to avoid naming issues)
            $table->foreign('activity_1_id')
                ->references('id')
                ->on('activities')
                ->cascadeOnDelete();
            
            $table->foreign('activity_2_id')
                ->references('id')
                ->on('activities')
                ->cascadeOnDelete();

            // Add indices for common queries
            $table->index(['user_id', 'resolved_at']);
            $table->index(['trip_id', 'resolved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_itinerary_conflicts');
    }
};
