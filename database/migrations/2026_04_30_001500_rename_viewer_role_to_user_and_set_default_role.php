<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $userRole = Role::where('name', 'user')->first();
        $viewerRole = Role::where('name', 'viewer')->first();

        if ($viewerRole !== null && $userRole === null) {
            $viewerRole->name = 'user';
            $viewerRole->save();
            $userRole = $viewerRole;
        }

        $userRole ??= Role::create(['name' => 'user']);

        $userRole->syncPermissions(
            Permission::where('name', 'like', 'finance.%.view')
                ->orWhere('name', 'module.finance')
                ->pluck('name')
                ->toArray()
        );

        if ($viewerRole !== null && $viewerRole->id !== $userRole->id) {
            $assignments = DB::table('model_has_roles')
                ->where('role_id', $viewerRole->id)
                ->get();

            foreach ($assignments as $assignment) {
                DB::table('model_has_roles')->updateOrInsert([
                    'role_id' => $userRole->id,
                    'model_type' => $assignment->model_type,
                    'model_id' => $assignment->model_id,
                ], []);
            }

            DB::table('model_has_roles')
                ->where('role_id', $viewerRole->id)
                ->delete();

            DB::table('role_has_permissions')
                ->where('role_id', $viewerRole->id)
                ->delete();

            $viewerRole->delete();
        }
    }

    public function down(): void
    {
        $viewerRole = Role::where('name', 'viewer')->first();
        $userRole = Role::where('name', 'user')->first();

        if ($userRole === null) {
            return;
        }

        if ($viewerRole === null) {
            $userRole->name = 'viewer';
            $userRole->save();

            return;
        }

        $assignments = DB::table('model_has_roles')
            ->where('role_id', $userRole->id)
            ->get();

        foreach ($assignments as $assignment) {
            DB::table('model_has_roles')->updateOrInsert([
                'role_id' => $viewerRole->id,
                'model_type' => $assignment->model_type,
                'model_id' => $assignment->model_id,
            ], []);
        }

        DB::table('model_has_roles')
            ->where('role_id', $userRole->id)
            ->delete();

        $viewerRole->syncPermissions($userRole->permissions->pluck('name')->toArray());
        $userRole->delete();
    }
};
