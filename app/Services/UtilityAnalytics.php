<?php

namespace App\Services;

use App\Jobs\SendUtilityAlert;
use App\Models\Property;
use App\Models\Utility;
use Carbon\Carbon;
use Psr\Log\LoggerInterface;

class UtilityAnalytics
{
    public function __construct(private LoggerInterface $logger) {}

    public function calculateTrends(Utility $utility, int $months = 12): array
    {
        $bills = $utility->utilityBills()
            ->where('date', '>=', now()->subMonths($months))
            ->orderBy('date')
            ->get();

        $monthlyData = [];
        $totalCost = 0;
        $maxCost = 0;
        $minCost = PHP_INT_MAX;

        foreach ($bills as $bill) {
            $monthKey = $bill->date->format('Y-m');
            $monthName = $bill->date->format('M Y');

            if (!isset($monthlyData[$monthKey])) {
                $monthlyData[$monthKey] = [
                    'month' => $monthName,
                    'cost' => 0,
                    'reading' => 0,
                    'count' => 0,
                ];
            }

            $monthlyData[$monthKey]['cost'] += $bill->cost;
            $monthlyData[$monthKey]['reading'] += $bill->reading ?? 0;
            $monthlyData[$monthKey]['count']++;

            $totalCost += $bill->cost;
            $maxCost = max($maxCost, $bill->cost);
            $minCost = min($minCost, $bill->cost);
        }

        // Calculate averages
        $averageMonthlyCost = count($monthlyData) > 0 ? $totalCost / count($monthlyData) : 0;

        // Determine trend
        $trend = $this->calculateTrend(array_values($monthlyData));

        // Calculate consumption pattern (by day of week if available)
        $consumptionPattern = $this->calculateConsumptionPattern($bills);

        return [
            'utility' => $utility,
            'months' => array_values($monthlyData),
            'average_monthly' => round($averageMonthlyCost, 2),
            'total_cost' => $totalCost,
            'max_cost' => $maxCost === PHP_INT_MAX ? 0 : $maxCost,
            'min_cost' => $minCost === PHP_INT_MAX ? 0 : $minCost,
            'trend' => $trend,
            'consumption_pattern' => $consumptionPattern,
        ];
    }

    public function getPropertyTrends(Property $property): array
    {
        $utilities = $property->utilities()->get();
        $utilityTrends = [];
        $totalCost = 0;

        foreach ($utilities as $utility) {
            $trends = $this->calculateTrends($utility);
            $utilityTrends[] = [
                'type' => $utility->type,
                'provider' => $utility->provider,
                'trends' => $trends,
            ];
            $totalCost += $trends['total_cost'];
        }

        return [
            'utilities' => $utilityTrends,
            'total_cost' => $totalCost,
        ];
    }

    public function checkAlert(Utility $utility): bool
    {
        $currentMonthBills = $utility->utilityBills()
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('cost') ?? 0;

        $trends = $this->calculateTrends($utility, 12);
        $averageCost = $trends['average_monthly'];

        // Alert if current month > 125% of average
        if ($currentMonthBills > ($averageCost * 1.25)) {
            $percentageOver = round((($currentMonthBills - $averageCost) / $averageCost) * 100, 2);

            SendUtilityAlert::dispatch($utility, $utility->user, [
                'current_cost' => $currentMonthBills,
                'average_cost' => $averageCost,
                'percentage_over' => $percentageOver,
            ]);

            $this->logger->info("Utility alert sent", [
                'utility_id' => $utility->id,
                'percentage_over' => $percentageOver,
            ]);

            return true;
        }

        return false;
    }

    public function getConsumptionByCategory(Property $property): array
    {
        $utilities = $property->utilities()->get();
        $consumption = [];

        foreach ($utilities as $utility) {
            if (!isset($consumption[$utility->type])) {
                $consumption[$utility->type] = [
                    'cost' => 0,
                    'consumption' => 0,
                ];
            }

            $bills = $utility->utilityBills()
                ->where('date', '>=', now()->subYear())
                ->get();

            foreach ($bills as $bill) {
                $consumption[$utility->type]['cost'] += $bill->cost;
                $consumption[$utility->type]['consumption'] += $bill->reading ?? 0;
            }
        }

        return $consumption;
    }

    private function calculateTrend(array $monthlyData): string
    {
        if (count($monthlyData) < 2) {
            return 'stable';
        }

        $costs = array_column($monthlyData, 'cost');
        $recent = array_slice($costs, -3);
        $early = array_slice($costs, 0, 3);

        $recentAvg = count($recent) > 0 ? array_sum($recent) / count($recent) : 0;
        $earlyAvg = count($early) > 0 ? array_sum($early) / count($early) : 0;

        if ($earlyAvg == 0) {
            return 'stable';
        }

        $change = (($recentAvg - $earlyAvg) / $earlyAvg) * 100;

        if ($change > 10) {
            return 'increasing';
        } elseif ($change < -10) {
            return 'decreasing';
        }

        return 'stable';
    }

    private function calculateConsumptionPattern(mixed $bills): array
    {
        $pattern = [];
        $dayOfWeekCounts = array_fill(0, 7, 0);
        $dayOfWeekReadings = array_fill(0, 7, 0);

        foreach ($bills as $bill) {
            $dayOfWeek = $bill->date->dayOfWeek;
            $dayOfWeekCounts[$dayOfWeek]++;
            $dayOfWeekReadings[$dayOfWeek] += $bill->reading ?? 0;
        }

        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        for ($i = 0; $i < 7; $i++) {
            if ($dayOfWeekCounts[$i] > 0) {
                $pattern[] = [
                    'day_of_week' => $days[$i],
                    'avg_reading' => round($dayOfWeekReadings[$i] / $dayOfWeekCounts[$i], 2),
                ];
            }
        }

        return $pattern;
    }
}
