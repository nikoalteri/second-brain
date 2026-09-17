<?php

use Illuminate\Support\Facades\Route;

Route::view('/cookie-policy', 'legal.cookie-policy')->name('cookie-policy');

Route::view('/{any?}', 'app')
    ->where('any', '^(?!hub|api|graphql|docs).*$')
    ->name('spa');
