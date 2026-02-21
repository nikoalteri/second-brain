<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Permessi moduli (dal tuo design)
        $modules = [
            'module.finance',
            'module.health',
            'module.productivity',
            'module.relations',
            'module.home',
            'module.cooking',
            'module.travel',
            'module.adminpanel',
        ];

        foreach ($modules as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Ruoli
        $superadmin = Role::firstOrCreate(['name' => 'superadmin']);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $user = Role::firstOrCreate(['name' => 'user']);

        // admin prende tutti i permessi moduli
        $admin->syncPermissions($modules);

        // user non ha permessi di default (li assegna superadmin)
    }
}
