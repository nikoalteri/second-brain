<?php

use Illuminate\Support\Facades\Route;

Route::view('/{any?}', 'app')
    ->where('any', '^(?!hub|api|graphql|docs).*$')
    ->name('spa');
