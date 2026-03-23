#!/usr/bin/env php
<?php
/**
 * Credit Card Calculation Validator
 * 
 * Validates actual bank statement values against calculated values
 * to determine which calculation method is correct.
 * 
 * Usage: php scripts/validate-credit-card-calculation.php <starting_debt> <interest_rate> <interest_actual> <principal_actual>
 * 
 * Example: php scripts/validate-credit-card-calculation.php 542 14 75.88 230.28
 */

if ($argc < 5) {
    echo "Usage: php validate-credit-card-calculation.php <starting_debt> <interest_rate> <interest_actual> <principal_actual>\n\n";
    echo "Arguments:\n";
    echo "  starting_debt       Starting debt balance (e.g., 542)\n";
    echo "  interest_rate       Annual interest rate (e.g., 14)\n";
    echo "  interest_actual     Interest shown on bank statement (e.g., 75.88)\n";
    echo "  principal_actual    Principal shown on bank statement (e.g., 230.28)\n";
    exit(1);
}

$startingDebt = (float) $argv[1];
$interestRate = (float) $argv[2];
$interestActual = (float) $argv[3];
$principalActual = (float) $argv[4];
$fixedPayment = 250.00;

// Calculate using MONTHLY method (current implementation)
$interestMonthly = $startingDebt * ($interestRate / 100);
$principalMonthly = $fixedPayment - $interestMonthly;

// Calculate using DAILY method approximation
// For a 20-day cycle: startingDebt * (rate / 365) * 20
$interestDaily20Days = $startingDebt * ($interestRate / 100 / 365) * 20;
$principalDaily20Days = $fixedPayment - $interestDaily20Days;

// Display comparison
echo "\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║           CREDIT CARD CALCULATION VALIDATION REPORT            ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

echo "INPUT DATA:\n";
echo "  Starting Debt:      €" . number_format($startingDebt, 2) . "\n";
echo "  Annual Interest:    {$interestRate}%\n";
echo "  Fixed Payment:      €" . number_format($fixedPayment, 2) . "\n";
echo "  Bank Interest:      €" . number_format($interestActual, 2) . "\n";
echo "  Bank Principal:     €" . number_format($principalActual, 2) . "\n\n";

echo "CALCULATED VS ACTUAL:\n";
echo str_repeat("─", 70) . "\n";
echo sprintf("%-30s | %-18s | %-18s\n", "Method", "Calculated", "Actual");
echo str_repeat("─", 70) . "\n";

// Monthly method
$monthlyInterestMatch = abs($interestMonthly - $interestActual) < 0.01;
$monthlyPrincipalMatch = abs($principalMonthly - $principalActual) < 0.01;
$monthlyStatus = ($monthlyInterestMatch && $monthlyPrincipalMatch) ? "✓ MATCH" : "✗ MISMATCH";

echo sprintf("%-30s | €%-16.2f | €%-16.2f\n", "Monthly Interest", $interestMonthly, $interestActual);
echo sprintf("%-30s | €%-16.2f | €%-16.2f %s\n", "Monthly Principal", $principalMonthly, $principalActual, $monthlyStatus);
echo "\n";

// Daily method (20-day approx)
$dailyInterestMatch = abs($interestDaily20Days - $interestActual) < 0.01;
$dailyPrincipalMatch = abs($principalDaily20Days - $principalActual) < 0.01;
$dailyStatus = ($dailyInterestMatch && $dailyPrincipalMatch) ? "✓ MATCH" : "✗ MISMATCH";

echo sprintf("%-30s | €%-16.2f | €%-16.2f\n", "Daily (20-day) Interest", $interestDaily20Days, $interestActual);
echo sprintf("%-30s | €%-16.2f | €%-16.2f %s\n", "Daily (20-day) Principal", $principalDaily20Days, $principalActual, $dailyStatus);
echo str_repeat("─", 70) . "\n\n";

// Analysis
echo "ANALYSIS:\n";

$monthlyMatches = $monthlyInterestMatch && $monthlyPrincipalMatch;
$dailyMatches = $dailyInterestMatch && $dailyPrincipalMatch;

if ($monthlyMatches && !$dailyMatches) {
    echo "✓ MONTHLY METHOD VALIDATES\n";
    echo "  The current implementation (debt × rate) is correct.\n";
    echo "  Action: Keep current code as-is.\n";
} elseif ($dailyMatches && !$monthlyMatches) {
    echo "✓ DAILY METHOD VALIDATES\n";
    echo "  The daily balance method (sum of daily rates) is correct.\n";
    echo "  Action: Switch issueCycle() to use calculateRevolvingPaymentBreakdownFromCycle()\n";
} elseif ($monthlyMatches && $dailyMatches) {
    echo "⚠ BOTH METHODS MATCH\n";
    echo "  This cycle's data fits both approaches. Need more data to distinguish.\n";
} else {
    echo "✗ NO MATCH\n";
    echo "  Neither method matches the bank statement.\n";
    echo "  Possible causes:\n";
    echo "    - Different cycle length (not 20 days)\n";
    echo "    - Fees or other charges included\n";
    echo "    - Different interest calculation timing\n";
}

echo "\n";

// Calculate variances
$monthlyInterestError = abs($interestMonthly - $interestActual);
$monthlyPrincipalError = abs($principalMonthly - $principalActual);
$dailyInterestError = abs($interestDaily20Days - $interestActual);
$dailyPrincipalError = abs($principalDaily20Days - $principalActual);

echo "ERROR ANALYSIS (variance from actual):\n";
echo sprintf("  Monthly Interest:  €%.4f error (%.2f%%)\n", 
    $monthlyInterestError, 
    ($monthlyInterestError / $interestActual) * 100
);
echo sprintf("  Monthly Principal: €%.4f error (%.2f%%)\n", 
    $monthlyPrincipalError, 
    ($monthlyPrincipalError / $principalActual) * 100
);
echo sprintf("  Daily Interest:    €%.4f error (%.2f%%)\n", 
    $dailyInterestError, 
    ($dailyInterestError / $interestActual) * 100
);
echo sprintf("  Daily Principal:   €%.4f error (%.2f%%)\n", 
    $dailyPrincipalError, 
    ($dailyPrincipalError / $principalActual) * 100
);

echo "\n";
