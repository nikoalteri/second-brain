<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CreditCardController;
use App\Http\Controllers\Api\V1\CreditCardCycleController;
use App\Http\Controllers\Api\V1\CreditCardExpenseController;
use App\Http\Controllers\Api\V1\CreditCardPaymentController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FinanceReportController;
use App\Http\Controllers\Api\V1\LoanController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SubscriptionFrequencyController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ─── Authentication (no auth guard required) ───────────────────────────
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });

    // ─── Read endpoints — 100 req/min ──────────────────────────────────────
    Route::middleware(['auth:sanctum', 'throttle:api-read'])->group(function () {
        Route::get('accounts', [AccountController::class, 'index']);
        Route::get('accounts/{account}', [AccountController::class, 'show']);
        Route::get('dashboard/upcoming-payments', [DashboardController::class, 'upcomingPayments']);

        Route::get('transactions', [TransactionController::class, 'index']);
        Route::get('transactions/{transaction}', [TransactionController::class, 'show']);

        Route::get('loans', [LoanController::class, 'index']);
        Route::get('loans/{loan}', [LoanController::class, 'show']);

        Route::get('credit-cards', [CreditCardController::class, 'index']);
        Route::get('credit-cards/{creditCard}', [CreditCardController::class, 'show']);

        Route::get('subscriptions', [SubscriptionController::class, 'index']);
        Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show']);
        Route::get('subscription-frequencies', [SubscriptionFrequencyController::class, 'index']);

        Route::get('reports/finance', [FinanceReportController::class, 'summary']);
        Route::get('reports/finance/details', [FinanceReportController::class, 'details']);
    });

    // ─── Write endpoints — 20 req/min ─────────────────────────────────────
    Route::middleware(['auth:sanctum', 'throttle:api-write'])->group(function () {
        Route::post('accounts', [AccountController::class, 'store']);
        Route::put('accounts/{account}', [AccountController::class, 'update']);
        Route::patch('accounts/{account}', [AccountController::class, 'update']);
        Route::delete('accounts/{account}', [AccountController::class, 'destroy']);

        Route::post('transactions', [TransactionController::class, 'store']);
        Route::put('transactions/{transaction}', [TransactionController::class, 'update']);
        Route::patch('transactions/{transaction}', [TransactionController::class, 'update']);
        Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy']);

        Route::post('loans', [LoanController::class, 'store']);
        Route::post('loans/{loan}/generate-schedule', [LoanController::class, 'generateSchedule']);
        Route::put('loans/{loan}', [LoanController::class, 'update']);
        Route::patch('loans/{loan}', [LoanController::class, 'update']);
        Route::delete('loans/{loan}', [LoanController::class, 'destroy']);

        Route::post('credit-cards', [CreditCardController::class, 'store']);
        Route::post('credit-cards/{creditCard}/cycles', [CreditCardCycleController::class, 'store']);
        Route::post('credit-cards/{creditCard}/cycles/{cycle}/issue', [CreditCardCycleController::class, 'issue']);
        Route::post('credit-cards/{creditCard}/payments/{payment}/mark-paid', [CreditCardPaymentController::class, 'markPaid']);
        Route::post('credit-cards/{creditCard}/expenses', [CreditCardExpenseController::class, 'store']);
        Route::put('credit-cards/{creditCard}', [CreditCardController::class, 'update']);
        Route::patch('credit-cards/{creditCard}', [CreditCardController::class, 'update']);
        Route::put('credit-cards/{creditCard}/cycles/{cycle}', [CreditCardCycleController::class, 'update']);
        Route::patch('credit-cards/{creditCard}/cycles/{cycle}', [CreditCardCycleController::class, 'update']);
        Route::put('credit-cards/{creditCard}/expenses/{expense}', [CreditCardExpenseController::class, 'update']);
        Route::patch('credit-cards/{creditCard}/expenses/{expense}', [CreditCardExpenseController::class, 'update']);
        Route::delete('credit-cards/{creditCard}', [CreditCardController::class, 'destroy']);
        Route::delete('credit-cards/{creditCard}/cycles/{cycle}', [CreditCardCycleController::class, 'destroy']);
        Route::delete('credit-cards/{creditCard}/payments/{payment}', [CreditCardPaymentController::class, 'destroy']);
        Route::delete('credit-cards/{creditCard}/expenses/{expense}', [CreditCardExpenseController::class, 'destroy']);

        Route::post('subscriptions', [SubscriptionController::class, 'store']);
        Route::put('subscriptions/{subscription}', [SubscriptionController::class, 'update']);
        Route::patch('subscriptions/{subscription}', [SubscriptionController::class, 'update']);
        Route::delete('subscriptions/{subscription}', [SubscriptionController::class, 'destroy']);
    });
});
