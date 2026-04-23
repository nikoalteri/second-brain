<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateUserSettingsRequest;
use App\Services\UserSettingsService;
use Illuminate\Http\JsonResponse;

class UserSettingsController extends Controller
{
    public function __construct(
        private readonly UserSettingsService $userSettingsService,
    ) {}

    /**
     * Update the authenticated user's frontend preferences.
     *
     * @group Authentication
     * @authenticated
     */
    public function update(UpdateUserSettingsRequest $request): JsonResponse
    {
        $user = $this->userSettingsService->update($request->user(), $request->validated());

        return response()->json([
            'user' => $user->toFrontendPayload(),
        ]);
    }
}
