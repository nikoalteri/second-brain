<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'App\Http\Middleware\ApiRateLimitMiddleware'])
    ->group(function () {
        // API routes
    });
