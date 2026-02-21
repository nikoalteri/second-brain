<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Permessi moduli (accesso base)
        $modules = [
            'module.finance', 'module.health', 'module.productivity',
            'module.relations', 'module.home', 'module.cooking',
            'module.travel', 'module.adminpanel',
        ];

        // Permessi granulari per risorsa
        $granular = [
            // Finance
            'finance.accounts.view', 'finance.accounts.create',
            'finance.accounts.edit', 'finance.accounts.delete',
            'finance.transactions.view', 'finance.transactions.create',
            'finance.transactions.edit', 'finance.transactions.delete',
            'finance.subscriptions.view', 'finance.subscriptions.create',
            'finance.subscriptions.edit', 'finance.subscriptions.delete',
            'finance.loans.view', 'finance.loans.create',
            'finance.loans.edit', 'finance.loans.delete',
            'finance.creditcards.view', 'finance.creditcards.create',
            'finance.creditcards.edit', 'finance.creditcards.delete',

            // Health
            'health.metrics.view', 'health.metrics.create',
            'health.metrics.edit', 'health.metrics.delete',
            'health.workouts.view', 'health.workouts.create',
            'health.workouts.edit', 'health.workouts.delete',
            'health.medical.view', 'health.medical.create',
            'health.medical.edit', 'health.medical.delete',

            // Productivity
            'productivity.habits.view', 'productivity.habits.create',
            'productivity.habits.edit', 'productivity.habits.delete',
            'productivity.goals.view', 'productivity.goals.create',
            'productivity.goals.edit', 'productivity.goals.delete',
            'productivity.notes.view', 'productivity.notes.create',
            'productivity.notes.edit', 'productivity.notes.delete',
            'productivity.journal.view', 'productivity.journal.create',
            'productivity.journal.edit', 'productivity.journal.delete',

            // Relations
            'relations.contacts.view', 'relations.contacts.create',
            'relations.contacts.edit', 'relations.contacts.delete',

            // Home
            'home.documents.view', 'home.documents.create',
            'home.documents.edit', 'home.documents.delete',
            'home.vehicles.view', 'home.vehicles.create',
            'home.vehicles.edit', 'home.vehicles.delete',

            // Cooking
            'cooking.recipes.view', 'cooking.recipes.create',
            'cooking.recipes.edit', 'cooking.recipes.delete',
            'cooking.mealplans.view', 'cooking.mealplans.create',
            'cooking.mealplans.edit', 'cooking.mealplans.delete',

            // Travel
            'travel.trips.view', 'travel.trips.create',
            'travel.trips.edit', 'travel.trips.delete',
            'travel.wishlist.view', 'travel.wishlist.create',
            'travel.wishlist.edit', 'travel.wishlist.delete',
        ];

        // Crea tutti i permessi
        foreach (array_merge($modules, $granular) as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Ruolo superadmin — bypass Gate::before, nessun permesso necessario
        Role::firstOrCreate(['name' => 'superadmin']);

        // Ruolo admin — tutto
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(array_merge($modules, $granular));

        // Ruolo finance_manager — solo finance
        $financeManager = Role::firstOrCreate(['name' => 'finance_manager']);
        $financeManager->syncPermissions(
            Permission::where('name', 'like', 'finance.%')
                ->orWhere('name', 'module.finance')
                ->pluck('name')
                ->toArray()
        );

        // Ruolo viewer — solo .view su tutto
        $viewer = Role::firstOrCreate(['name' => 'viewer']);
        $viewer->syncPermissions(
            Permission::where('name', 'like', '%.view')
                ->orWhere('name', 'like', 'module.%')
                ->pluck('name')
                ->toArray()
        );
    }
}

