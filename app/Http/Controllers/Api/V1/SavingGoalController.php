<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSavingGoalRequest;
use App\Http\Requests\Api\UpdateSavingGoalRequest;
use App\Http\Resources\Api\SavingGoalResource;
use App\Models\Account;
use App\Models\SavingGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
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
            ->with('account')
            ->allowedFilters(AllowedFilter::exact('status'))
            ->allowedSorts('target_date', 'target_amount', 'created_at')
            ->defaultSort('-created_at')
            ->cursorPaginate($request->integer('per_page', 20));

        return SavingGoalResource::collection($goals);
    }

    /** @group Saving Goals @authenticated */
    public function store(StoreSavingGoalRequest $request): JsonResponse
    {
        $this->authorize('create', SavingGoal::class);

        // Scoped lookup: HasUserScoping filters non-superadmin to their own accounts, so a
        // foreign account id 404s. For superadmin (exempt from that scoping in Filament's
        // account picker), the goal's owner follows the CHOSEN account, not the acting admin.
        $account = Account::query()->findOrFail($request->validated('account_id'));

        $goal = SavingGoal::create(array_merge(
            $request->validated(),
            ['user_id' => $account->user_id],
        ));
        $goal->load('account');

        return (new SavingGoalResource($goal))->response()->setStatusCode(201);
    }

    /** @group Saving Goals @authenticated */
    public function show(Request $request, SavingGoal $savingGoal): SavingGoalResource
    {
        $this->authorize('view', $savingGoal);
        $savingGoal->load('account');

        return new SavingGoalResource($savingGoal);
    }

    /** @group Saving Goals @authenticated */
    public function update(UpdateSavingGoalRequest $request, SavingGoal $savingGoal): SavingGoalResource
    {
        $this->authorize('update', $savingGoal);

        $data = $request->validated();

        if (array_key_exists('account_id', $data)) {
            $account = Account::query()->findOrFail($data['account_id']);

            if ((int) $account->user_id !== (int) $savingGoal->user_id) {
                throw ValidationException::withMessages([
                    'account_id' => 'The account must belong to the same user as the goal.',
                ]);
            }
        }

        $savingGoal->update($data);
        $savingGoal->load('account');

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
