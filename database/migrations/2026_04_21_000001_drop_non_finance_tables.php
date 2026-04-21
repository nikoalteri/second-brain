<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop all non-finance tables as part of the Fluxa reconversion.
     * Run this migration on existing databases to clean up legacy modules.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            // Travel
            'trip_itinerary_conflicts',
            'trip_expenses',
            'trip_participants',
            'trip_budgets',
            'activities',
            'itineraries',
            'flights',
            'hotels',
            'destinations',
            'trips',
            // Home
            'property_maintenance_records',
            'maintenance_tasks',
            'maintenance_records',
            'inventories',
            'inventory_categories',
            'utility_bills',
            'utilities',
            'properties',
            'vehicles',
            // Cooking
            'meals',
            'ingredients',
            'recipes',
            // Health
            'blood_tests',
            'workouts',
            'medications',
            'medical_records',
            'health_records',
            // Productivity
            'projects',
            'notes',
            'journal_entries',
            'goals',
            'habits',
            // Relationships
            'messages',
            'events',
            'documents',
            'contacts',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Intentionally irreversible — re-run previous migrations to restore.
    }
};
