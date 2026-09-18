<?php

use App\Http\Controllers\SchedulerHealthController;
use Illuminate\Support\Facades\Route;

Route::view('/cookie-policy', 'legal.cookie-policy')->name('cookie-policy');

Route::get('/health/scheduler', SchedulerHealthController::class)
    ->middleware('throttle:60,1')
    ->name('health.scheduler');

Route::view('/{any?}', 'app')
    ->where('any', '^(?!hub|api|graphql|docs|health).*$')
    ->name('spa');
