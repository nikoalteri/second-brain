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
            'module.finance',
            'module.adminpanel',
        ];

        // Permessi granulari finance
        $granular = [
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
        ];

        $all = array_merge($modules, $granular);

        // Rimuovi permessi non-finance rimasti da conversioni precedenti
        Permission::whereNotIn('name', $all)->delete();

        // Crea permessi finance
        foreach ($all as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Ruolo superadmin — bypass Gate::before, nessun permesso necessario
        Role::firstOrCreate(['name' => 'superadmin']);

        // Ruolo admin — tutto finance
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($all);

        // Ruolo finance_manager — solo finance
        $financeManager = Role::firstOrCreate(['name' => 'finance_manager']);
        $financeManager->syncPermissions(
            Permission::where('name', 'like', 'finance.%')
                ->orWhere('name', 'module.finance')
                ->pluck('name')
                ->toArray()
        );

        // Ruolo viewer — solo .view su finance
        $viewer = Role::firstOrCreate(['name' => 'viewer']);
        $viewer->syncPermissions(
            Permission::where('name', 'like', 'finance.%.view')
                ->orWhere('name', 'module.finance')
                ->pluck('name')
                ->toArray()
        );

        // Rimuovi ruoli non-finance eventualmente rimasti
        Role::whereNotIn('name', ['superadmin', 'admin', 'finance_manager', 'viewer'])->delete();
    }
}

