<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Page size from ?per_page, kept between 1 and $max. Without a cap a single request could
     * ask for every row of a table; without a floor 0 or a negative value falls back to the
     * framework default instead of being rejected.
     */
    protected function perPage(Request $request, int $default = 20, int $max = 100): int
    {
        return max(1, min($max, $request->integer('per_page', $default)));
    }
}
