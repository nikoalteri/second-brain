<?php

namespace App\Services;

use App\Models\Property;
use App\Models\User;

class PropertyDashboardService
{
    public function __construct(
        private PropertyService $propertyService,
        private MaintenanceService $maintenanceService,
        private UtilityAnalytics $utilityAnalytics,
        private DeprecationCalculator $depreciationCalculator
    ) {}

    public function getMetrics(Property $property, User $user): array
    {
        $metrics = $this->propertyService->getPropertyWithMetrics($property);

        // Maintenance metrics
        $allTasks = $this->maintenanceService->getTasksForProperty($property);
        $completedThisYear = $property->propertyMaintenanceRecords()
            ->whereYear('date', now()->year)
            ->distinct('maintenance_task_id')
            ->count();

        $overdueTasks = $property->maintenanceTasks()
            ->where('next_due_date', '<', now())
            ->where('status', 'active')
            ->count();

        $topUpcomingTasks = $property->maintenanceTasks()
            ->where('next_due_date', '>=', now())
            ->where('next_due_date', '<=', now()->addMonths(12))
            ->where('status', 'active')
            ->orderBy('next_due_date')
            ->limit(5)
            ->get()
            ->map(fn($task) => [
                'name' => $task->name,
                'due_date' => $task->next_due_date?->toDateString(),
                'type' => $task->type,
            ]);

        $maintenanceMetrics = [
            'total_tasks' => $metrics['maintenance_count'],
            'completed_this_year' => $completedThisYear,
            'due_soon' => $metrics['due_soon'],
            'overdue' => $overdueTasks,
            'next_tasks' => $topUpcomingTasks->toArray(),
            'costs_ytd' => $metrics['cost_ytd'],
        ];

        // Utility metrics
        $utilities = $property->utilities()->get();
        $currentMonthCost = $utilities
            ->sum(fn($u) => $u->utilityBills()
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->sum('cost'));

        $lastMonthCost = $utilities
            ->sum(fn($u) => $u->utilityBills()
                ->whereMonth('date', now()->subMonth()->month)
                ->whereYear('date', now()->subMonth()->year)
                ->sum('cost'));

        $allBills = $property->utilities()
            ->join('utility_bills', 'utilities.id', '=', 'utility_bills.utility_id')
            ->where('utility_bills.date', '>=', now()->subYear())
            ->sum('utility_bills.cost') ?? 0;

        $monthlyAverage = $allBills / 12;

        // Determine trend from trends data
        $trend = 'stable';
        $propertyTrends = $this->utilityAnalytics->getPropertyTrends($property);
        if (!empty($propertyTrends['utilities']) && isset($propertyTrends['utilities'][0]['trends']['trend'])) {
            $trend = $propertyTrends['utilities'][0]['trends']['trend'];
        }

        $utilityMetrics = [
            'accounts' => $metrics['utilities'],
            'cost_this_month' => round($currentMonthCost, 2),
            'cost_last_month' => round($lastMonthCost, 2),
            'monthly_average' => round($monthlyAverage, 2),
            'trend' => $trend,
        ];

        // Inventory metrics
        $report = $this->depreciationCalculator->getPropertyInventoryReport($property);

        $inventoryMetrics = [
            'items' => $property->inventories()->count(),
            'original_value' => $report['total_original_value'],
            'current_value' => $report['total_current_value'],
            'total_depreciation' => $report['total_depreciation'],
        ];

        // Alerts
        $alerts = [];

        if ($overdueTasks > 0) {
            $alerts[] = [
                'type' => 'maintenance_overdue',
                'message' => "$overdueTasks maintenance task(s) overdue - please schedule!",
            ];
        }

        if ($metrics['due_soon'] > 0) {
            $alerts[] = [
                'type' => 'maintenance_due_soon',
                'message' => "{$metrics['due_soon']} maintenance task(s) due in next 30 days",
            ];
        }

        $utilityAlertTriggered = false;
        foreach ($utilities as $utility) {
            if ($this->utilityAnalytics->checkAlert($utility)) {
                $utilityAlertTriggered = true;
                break;
            }
        }

        if ($utilityAlertTriggered) {
            $alerts[] = [
                'type' => 'utility_alert',
                'message' => 'Utility usage higher than normal - check consumption trends',
            ];
        }

        return [
            'property' => [
                'id' => $property->id,
                'address' => $property->address,
                'type' => $property->property_type,
                'value' => $property->estimated_value,
            ],
            'maintenance' => $maintenanceMetrics,
            'utilities' => $utilityMetrics,
            'inventory' => $inventoryMetrics,
            'alerts' => $alerts,
        ];
    }

    public function getPropertyReport(Property $property): array
    {
        $metrics = $this->getMetrics($property, $property->user);
        $maintenanceHistory = $property->propertyMaintenanceRecords()
            ->with('maintenanceTask')
            ->latest('date')
            ->limit(10)
            ->get()
            ->map(fn($record) => [
                'date' => $record->date->toDateString(),
                'task' => $record->maintenanceTask->name ?? 'Unknown',
                'cost' => $record->cost,
                'contractor' => $record->contractor,
            ]);

        $inventoryReport = $this->depreciationCalculator->getPropertyInventoryReport($property);

        $utilityTrends = $this->utilityAnalytics->getPropertyTrends($property);

        return [
            'report_date' => now()->toDateString(),
            'property' => $metrics['property'],
            'maintenance' => $metrics['maintenance'],
            'maintenance_history' => $maintenanceHistory->toArray(),
            'utilities' => $metrics['utilities'],
            'utility_trends' => $utilityTrends,
            'inventory' => $metrics['inventory'],
            'inventory_report' => $inventoryReport,
            'alerts' => $metrics['alerts'],
        ];
    }
}
