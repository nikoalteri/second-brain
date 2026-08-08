<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConfirmTwoFactorRequest;
use App\Http\Requests\Api\DisableTwoFactorRequest;
use App\Http\Requests\Api\RegenerateRecoveryCodesRequest;
use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * @group Two-Factor Authentication
 *
 * Endpoints for enabling, confirming, and disabling TOTP two-factor authentication.
 */
class TwoFactorAuthController extends Controller
{
    public function __construct(private readonly TwoFactorAuthService $twoFactor) {}

    /**
     * Start enrollment: generate a pending secret (not yet active until confirmed).
     *
     * @group Two-Factor Authentication
     * @authenticated
     */
    public function enable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return response()->json(['message' => 'Two-factor authentication is already enabled.'], 422);
        }

        $secret = $this->twoFactor->generateSecretKey();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => null])->save();

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => $this->twoFactor->otpAuthUrl($user, $secret),
        ]);
    }

    /**
     * Confirm enrollment with a TOTP code from the authenticator app, activating 2FA and
     * issuing one-time recovery codes.
     *
     * @group Two-Factor Authentication
     * @authenticated
     */
    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json(['message' => 'Call enable first to generate a secret.'], 422);
        }

        if (! $this->twoFactor->verifyCode($user, $request->validated('code'))) {
            return response()->json(['message' => 'Invalid code.'], 422);
        }

        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Two-factor authentication enabled.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable two-factor authentication after confirming the account password.
     *
     * @group Two-Factor Authentication
     * @authenticated
     */
    public function disable(DisableTwoFactorRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['message' => 'Incorrect password.'], 422);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json(['message' => 'Two-factor authentication disabled.']);
    }

    /**
     * Invalidate existing recovery codes and issue a fresh set.
     *
     * @group Two-Factor Authentication
     * @authenticated
     */
    public function regenerateRecoveryCodes(RegenerateRecoveryCodesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return response()->json(['message' => 'Two-factor authentication is not enabled.'], 422);
        }

        if (! Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['message' => 'Incorrect password.'], 422);
        }

        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $recoveryCodes])->save();

        return response()->json(['recovery_codes' => $recoveryCodes]);
    }
}
