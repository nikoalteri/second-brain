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
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->index();
            
            // Trip reference
            $table->foreignId('trip_id')
                ->constrained('trips')
                ->onDelete('cascade')
                ->index();
            
            // Itinerary reference
            $table->foreignId('itinerary_id')
                ->constrained('itineraries')
                ->onDelete('cascade')
                ->index();
            
            // Activity references for conflicting activities
            $table->foreignId('activity_1_id')
                ->constrained('activities')
                ->onDelete('cascade')
                ->index();
            
            $table->foreignId('activity_2_id')
                ->constrained('activities')
                ->onDelete('cascade')
                ->index();
            
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
