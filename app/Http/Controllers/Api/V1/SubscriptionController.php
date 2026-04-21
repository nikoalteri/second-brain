<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSubscriptionRequest;
use App\Http\Requests\Api\UpdateSubscriptionRequest;
use App\Http\Resources\Api\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SubscriptionController extends Controller
{
    /**
     * @group Subscriptions
     * @authenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $subscriptions = QueryBuilder::for(Subscription::class)
            ->where('user_id', $request->user()->id)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('frequency'),
                AllowedFilter::exact('account_id'),
            ])
            ->allowedSorts(['next_renewal_date', 'monthly_cost', 'annual_cost', 'created_at'])
            ->defaultSort('next_renewal_date')
            ->cursorPaginate($request->integer('per_page', 20));

        return SubscriptionResource::collection($subscriptions);
    }

    /** @group Subscriptions @authenticated */
    public function store(StoreSubscriptionRequest $request): Response
    {
        $this->authorize('create', Subscription::class);

        $subscription = Subscription::create(array_merge($request->validated(), [
            'user_id' => $request->user()->id,
        ]));

        return (new SubscriptionResource($subscription))->response()->setStatusCode(201);
    }

    /** @group Subscriptions @authenticated */
    public function show(Request $request, Subscription $subscription): SubscriptionResource
    {
        $this->authorize('view', $subscription);

        return new SubscriptionResource($subscription);
    }

    /** @group Subscriptions @authenticated */
    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): SubscriptionResource
    {
        $this->authorize('update', $subscription);

        $subscription->update($request->validated());

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
