<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SubscriptionFrequencyResource;
use App\Models\SubscriptionFrequency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionFrequencyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $frequencies = SubscriptionFrequency::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return SubscriptionFrequencyResource::collection($frequencies);
    }
}
