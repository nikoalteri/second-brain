<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Property;
use Carbon\Carbon;
use Psr\Log\LoggerInterface;

class DeprecationCalculator
{
    public function __construct(private LoggerInterface $logger) {}

    public function calculateValue(Inventory $item): float
    {
        if (!$item->purchase_date || !$item->category) {
            return (float) $item->value;
        }

        $yearsOwned = $item->purchase_date->diffInYears(Carbon::now());
        $depreciationRate = $item->category->depreciation_rate / 100;

        // Compound depreciation formula: V = P * (1 - r)^n
        $currentValue = $item->value * pow(1 - $depreciationRate, $yearsOwned);

        return max(round($currentValue, 2), 0);
    }

    public function insuranceValue(Inventory $item): float
    {
        $currentValue = $this->calculateValue($item);

        // Round up to nearest 100
        return ceil($currentValue / 100) * 100;
    }

    public function getPropertyInventoryReport(Property $property): array
    {
        $inventories = $property->inventories()->with('category')->get();

        $totalOriginalValue = 0;
        $totalCurrentValue = 0;
        $byCategory = [];

        foreach ($inventories as $item) {
            $currentValue = $this->calculateValue($item);
            $categoryName = $item->category->name ?? 'Uncategorized';

            $totalOriginalValue += $item->value;
            $totalCurrentValue += $currentValue;

            if (!isset($byCategory[$categoryName])) {
                $byCategory[$categoryName] = [
                    'category' => $categoryName,
                    'items' => 0,
                    'original' => 0,
                    'current' => 0,
                    'depreciation_rate' => $item->category->depreciation_rate ?? 0,
                ];
            }

            $byCategory[$categoryName]['items']++;
            $byCategory[$categoryName]['original'] += $item->value;
            $byCategory[$categoryName]['current'] += $currentValue;
        }

        $totalDepreciation = $totalOriginalValue - $totalCurrentValue;

        $this->logger->info("Property inventory report calculated", [
            'property_id' => $property->id,
            'total_original' => $totalOriginalValue,
            'total_current' => $totalCurrentValue,
        ]);

        return [
            'total_original_value' => round($totalOriginalValue, 2),
            'total_current_value' => round($totalCurrentValue, 2),
            'total_depreciation' => round($totalDepreciation, 2),
            'by_category' => array_values($byCategory),
        ];
    }

    public function getAnnualDepreciation(Property $property): float
    {
        $inventories = $property->inventories()->with('category')->get();

        $totalAnnualDepreciation = 0;

        foreach ($inventories as $item) {
            if (!$item->category) {
                continue;
            }

            // Annual depreciation = original_value * depreciation_rate
            $annualDepreciation = $item->value * ($item->category->depreciation_rate / 100);
            $totalAnnualDepreciation += $annualDepreciation;
        }

        return round($totalAnnualDepreciation, 2);
    }
}
