<?php

namespace App\Services;

use Spatie\Permission\Models\Permission;
use Illuminate\Support\Collection;

class PermissionService
{
    /**
     * Get all permissions grouped by module
     */
    public function getGroupedPermissions(): array
    {
        $permissions = Permission::pluck('name')->toArray();

        $grouped = [
            'Module Access' => [],
            'Finance' => [],
            'Health' => [],
            'Productivity' => [],
            'Relations' => [],
            'Home' => [],
            'Cooking' => [],
            'Travel' => [],
            'Admin Panel' => [],
        ];

        foreach ($permissions as $perm) {
            if (str_starts_with($perm, 'module.')) {
                $grouped['Module Access'][$perm] = $this->formatLabel($perm);
            } elseif (str_starts_with($perm, 'finance.')) {
                $grouped['Finance'][$perm] = $this->formatLabel($perm);
            } elseif (str_starts_with($perm, 'health.')) {
                $grouped['Health'][$perm] = $this->formatLabel($perm);
            } elseif (str_starts_with($perm, 'productivity.')) {
                $grouped['Productivity'][$perm] = $this->formatLabel($perm);
            } elseif (str_starts_with($perm, 'relations.')) {
                $grouped['Relations'][$perm] = $this->formatLabel($perm);
            } elseif (str_starts_with($perm, 'home.')) {
                $grouped['Home'][$perm] = $this->formatLabel($perm);
            } elseif (str_starts_with($perm, 'cooking.')) {
                $grouped['Cooking'][$perm] = $this->formatLabel($perm);
            } elseif (str_starts_with($perm, 'travel.')) {
                $grouped['Travel'][$perm] = $this->formatLabel($perm);
            }
        }

        // Remove empty groups
        return array_filter($grouped, fn($items) => !empty($items));
    }

    /**
     * Convert permission name to readable label
     * finance.accounts.view -> Finance / Accounts / View
     */
    private function formatLabel(string $permission): string
    {
        $parts = explode('.', $permission);
        return implode(' / ', array_map(fn($part) => ucfirst($part), $parts));
    }
}
