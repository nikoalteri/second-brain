<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSavingGoalRequest;
use App\Http\Requests\Api\UpdateSavingGoalRequest;
use App\Http\Resources\Api\SavingGoalResource;
use App\Models\SavingGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Saving Goals
 *
 * Endpoints for managing saving goals.
 */
class SavingGoalController extends Controller
{
    /** @group Saving Goals @authenticated */
    public function index(Request $request): AnonymousResourceCollection
    {
        $goals = QueryBuilder::for(SavingGoal::class)
            ->when(
                ! $request->user()->hasRole('superadmin'),
                fn ($query) => $query->where('user_id', $request->user()->id)
            )
            ->allowedFilters(AllowedFilter::exact('status'))
            ->allowedSorts('target_date', 'target_amount', 'current_amount', 'created_at')
            ->defaultSort('-created_at')
            ->cursorPaginate($request->integer('per_page', 20));

        return SavingGoalResource::collection($goals);
    }

    /** @group Saving Goals @authenticated */
    public function store(StoreSavingGoalRequest $request): JsonResponse
    {
        $this->authorize('create', SavingGoal::class);

        $goal = SavingGoal::create(array_merge(
            $request->validated(),
            ['user_id' => $request->user()->id],
        ));

        return (new SavingGoalResource($goal))->response()->setStatusCode(201);
    }

    /** @group Saving Goals @authenticated */
    public function show(Request $request, SavingGoal $savingGoal): SavingGoalResource
    {
        $this->authorize('view', $savingGoal);
        $savingGoal->load(['contributions' => fn ($query) => $query->latest('date')->with('account')]);

        return new SavingGoalResource($savingGoal);
    }

    /** @group Saving Goals @authenticated */
    public function update(UpdateSavingGoalRequest $request, SavingGoal $savingGoal): SavingGoalResource
    {
        $this->authorize('update', $savingGoal);

        $savingGoal->update($request->validated());

        return new SavingGoalResource($savingGoal);
    }

    /** @group Saving Goals @authenticated @response 204 {} */
    public function destroy(Request $request, SavingGoal $savingGoal): Response
    {
        $this->authorize('delete', $savingGoal);

        $savingGoal->delete();

        return response()->noContent();
    }
}
