<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateUserSettingsRequest;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;

class UserSettingsController extends Controller
{
    /**
     * Update the authenticated user's frontend preferences.
     *
     * @group Authentication
     * @authenticated
     */
    public function update(UpdateUserSettingsRequest $request): JsonResponse
    {
        $user = $request->user();

        foreach ($request->validated() as $key => $value) {
            $setting = $user->userSettings()
                ->withTrashed()
                ->firstOrNew(['setting_key' => $key]);

            $setting->setting_value = UserSetting::normalizeValue($key, $value);
            $setting->deleted_at = null;
            $setting->save();
        }

        $user->load('userSettings');

        return response()->json([
            'user' => $user->toFrontendPayload(),
        ]);
    }
}
