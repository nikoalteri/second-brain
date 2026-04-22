<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSubscriptionRequest;
use App\Http\Requests\Api\UpdateSubscriptionRequest;
use App\Http\Resources\Api\SubscriptionResource;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Subscriptions
 *
 * Endpoints for managing subscriptions.
 */
class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptionService) {}

    /**
     * @group Subscriptions
     * @authenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $subscriptions = QueryBuilder::for(Subscription::class)
            ->when(
                ! $request->user()->hasRole('superadmin'),
                fn ($query) => $query->where('user_id', $request->user()->id)
            )
            ->with(['account', 'creditCard', 'category', 'frequencyOption'])
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::callback('frequency', function ($query, $value) {
                    $query->whereHas('frequencyOption', fn ($frequencyQuery) => $frequencyQuery->where('slug', $value));
                }),
                AllowedFilter::exact('subscription_frequency_id'),
                AllowedFilter::exact('account_id'),
                AllowedFilter::exact('credit_card_id'),
            )
            ->allowedSorts('next_renewal_date', 'monthly_cost', 'annual_cost', 'created_at')
            ->defaultSort('next_renewal_date')
            ->cursorPaginate($request->integer('per_page', 20));

        return SubscriptionResource::collection($subscriptions);
    }

    /** @group Subscriptions @authenticated */
    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $this->authorize('create', Subscription::class);

        $subscription = Subscription::create(array_merge(
            $this->subscriptionService->prepareApiAttributes($request->validated()),
            [
            'user_id' => $request->user()->id,
            ]
        ));
        $subscription->load(['account', 'creditCard', 'category', 'frequencyOption']);

        return (new SubscriptionResource($subscription))->response()->setStatusCode(201);
    }

    /** @group Subscriptions @authenticated */
    public function show(Request $request, Subscription $subscription): SubscriptionResource
    {
        $this->authorize('view', $subscription);
        $subscription->load(['account', 'creditCard', 'category', 'frequencyOption']);

        return new SubscriptionResource($subscription);
    }

    /** @group Subscriptions @authenticated */
    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): SubscriptionResource
    {
        $this->authorize('update', $subscription);

        $subscription->update($this->subscriptionService->prepareApiAttributes($request->validated(), $subscription));
        $subscription->load(['account', 'creditCard', 'category', 'frequencyOption']);

        return new SubscriptionResource($subscription);
    }

    /** @group Subscriptions @authenticated @response 204 {} */
    public function destroy(Request $request, Subscription $subscription): Response
    {
        $this->authorize('delete', $subscription);

        $subscription->delete();

        return response()->noContent();
    }
}
