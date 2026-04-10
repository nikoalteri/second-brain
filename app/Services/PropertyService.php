<?php

namespace App\Services;

use App\Models\Property;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

class PropertyService
{
    public function __construct(private LoggerInterface $logger) {}

    public function create(User $user, array $data): Property
    {
        // Validate lease date ranges
        if (!empty($data['lease_start_date']) && !empty($data['lease_end_date'])) {
            $start = new \DateTime($data['lease_start_date']);
            $end = new \DateTime($data['lease_end_date']);
            if ($start > $end) {
                throw ValidationException::withMessages([
                    'lease_dates' => 'Lease start date must be before lease end date.',
                ]);
            }
        }

        $property = new Property($data);
        $property->user_id = $user->id;
        $property->save();

        $this->logger->info("Property created", [
            'property_id' => $property->id,
            'user_id' => $user->id,
            'address' => $data['address'] ?? null,
        ]);

        return $property;
    }

    public function update(Property $property, array $data): Property
    {
        // Validate lease date ranges if dates are provided
        if (!empty($data['lease_start_date']) && !empty($data['lease_end_date'])) {
            $start = new \DateTime($data['lease_start_date']);
            $end = new \DateTime($data['lease_end_date']);
            if ($start > $end) {
                throw ValidationException::withMessages([
                    'lease_dates' => 'Lease start date must be before lease end date.',
                ]);
            }
        }

        $property->update($data);

        $this->logger->info("Property updated", [
            'property_id' => $property->id,
        ]);

        return $property;
    }

    public function getPropertyWithMetrics(Property $property): array
    {
        $maintenanceTasks = $property->maintenanceTasks()->count();
        $dueSoon = $property->maintenanceTasks()
            ->where('next_due_date', '<=', now()->addDays(30))
            ->where('next_due_date', '>=', now())
            ->count();

        $utilityCount = $property->utilities()->count();

        $inventoryValue = $property->inventories()
            ->sum('value') ?? 0;

        $lastMaintenance = $property->propertyMaintenanceRecords()
            ->latest('date')
            ->first()?->date;

        $maintenanceCostYtd = $property->propertyMaintenanceRecords()
            ->whereYear('date', now()->year)
            ->sum('cost') ?? 0;

        $utilityCostYtd = $property->utilities()
            ->join('utility_bills', 'utilities.id', '=', 'utility_bills.utility_id')
            ->whereYear('utility_bills.date', now()->year)
            ->sum('utility_bills.cost') ?? 0;

        $costYtd = $maintenanceCostYtd + $utilityCostYtd;

        return [
            'property' => $property,
            'maintenance_count' => $maintenanceTasks,
            'due_soon' => $dueSoon,
            'utilities' => $utilityCount,
            'inventory_value' => $inventoryValue,
            'last_maintenance' => $lastMaintenance,
            'cost_ytd' => $costYtd,
        ];
    }

    public function delete(Property $property): bool
    {
        $result = $property->delete();

        $this->logger->info("Property deleted", [
            'property_id' => $property->id,
        ]);

        return $result;
    }
}
