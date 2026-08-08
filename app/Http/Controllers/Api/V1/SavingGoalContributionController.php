<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSavingGoalContributionRequest;
use App\Http\Resources\Api\SavingGoalContributionResource;
use App\Models\SavingGoal;
use App\Models\SavingGoalContribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SavingGoalContributionController extends Controller
{
    public function store(StoreSavingGoalContributionRequest $request, SavingGoal $savingGoal): JsonResponse
    {
        $this->authorize('update', $savingGoal);

        $contribution = $savingGoal->contributions()->create(array_merge(
            $request->validated(),
            ['user_id' => $savingGoal->user_id],
        ));
        $contribution->load('account');

        return (new SavingGoalContributionResource($contribution))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(SavingGoal $savingGoal, SavingGoalContribution $contribution): Response
    {
        $this->assertContributionBelongsToGoal($savingGoal, $contribution);
        $this->authorize('update', $savingGoal);

        $contribution->delete();

        return response()->noContent();
    }

    private function assertContributionBelongsToGoal(SavingGoal $savingGoal, SavingGoalContribution $contribution): void
    {
        abort_unless((int) $contribution->saving_goal_id === (int) $savingGoal->id, 404);
    }
}
