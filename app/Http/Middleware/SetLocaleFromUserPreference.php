<?php

namespace App\Http\Middleware;

use App\Support\Localization\SupportedLocales;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromUserPreference
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = SupportedLocales::appLocale($request->user()?->preferredLocale());

        App::setLocale($locale);
        Carbon::setLocale($locale);
        Number::useLocale($locale);

        return $next($request);
    }
}
