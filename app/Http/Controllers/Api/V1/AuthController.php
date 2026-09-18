<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Http\Requests\Api\TwoFactorLoginRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Models\User;
use App\Services\TwoFactorAuthService;
use App\Services\UserService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;

/**
 * @group Authentication
 *
 * Endpoints for user authentication and token management.
 */
class AuthController extends Controller
{
    private const CONSUMED_REFRESH_TOKEN = 'refresh:used';
    private const REFRESH_GRACE_SECONDS = 15;
    private const TWO_FACTOR_CACHE_PREFIX = 'two_factor_challenge:';

    public function __construct(
        private readonly UserService $userService,
        private readonly TwoFactorAuthService $twoFactor,
    ) {
    }

    /**
     * Authenticate user and issue access + refresh tokens.
     *
     * @group Authentication
     * @unauthenticated
     * @bodyParam email string required User email. Example: user@example.com
     * @bodyParam password string required User password (min 8 chars). Example: secret1234
     * @response 200 {"access_token":"1|...","refresh_token":"2|...","token_type":"Bearer","expires_in":1800}
     * @response 401 {"message":"Invalid credentials."}
     * @response 422 {"message":"Validation failed.","errors":{"email":["The email field is required."]}}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->is_active === false) {
            Auth::logout();

            return response()->json(['message' => 'This account is disabled.'], 403);
        }

        if ($user->hasTwoFactorEnabled()) {
            $challenge = (string) Str::uuid();
            Cache::put(self::TWO_FACTOR_CACHE_PREFIX . $challenge, $user->id, now()->addMinutes(5));

            return response()->json([
                'two_factor_required' => true,
                'two_factor_token' => $challenge,
            ]);
        }

        $user->tokens()->delete();

        return response()->json($this->issueTokens($user));
    }

    /**
     * Complete a login that required two-factor authentication: exchange the short-lived
     * challenge token plus a TOTP (or recovery) code for real access/refresh tokens. No
     * Sanctum token exists for this user until this step succeeds.
     *
     * @group Authentication
     * @unauthenticated
     */
    public function twoFactorLogin(TwoFactorLoginRequest $request): JsonResponse
    {
        $cacheKey = self::TWO_FACTOR_CACHE_PREFIX . $request->validated('two_factor_token');
        $userId = Cache::get($cacheKey);

        if (! $userId) {
            return response()->json(['message' => 'This login challenge has expired. Please sign in again.'], 422);
        }

        $user = User::findOrFail($userId);

        if ($user->is_active === false) {
            Cache::forget($cacheKey);

            return response()->json(['message' => 'This account is disabled.'], 403);
        }

        $code = $request->validated('code');

        $verified = $this->twoFactor->verifyCode($user, $code) || $this->twoFactor->useRecoveryCode($user, $code);

        if (! $verified) {
            return response()->json(['message' => 'Invalid code.'], 422);
        }

        Cache::forget($cacheKey);
        $user->tokens()->delete();

        return response()->json($this->issueTokens($user));
    }

    /**
     * Register a new user and issue access + refresh tokens.
     *
     * @group Authentication
     * @unauthenticated
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());
        Role::findOrCreate('user');
        $user->assignRole('user');

        return response()->json($this->issueTokens($user), 201);
    }

    /**
     * Exchange a refresh token for a new access token AND a new refresh token. The presented
     * refresh token is consumed: presenting it again is treated as a stolen token and revokes the
     * user's whole session, except for a short window in which a second, simultaneous refresh
     * (for example from another tab) is told to retry instead.
     *
     * @group Authentication
     * @unauthenticated
     * @response 200 {"access_token":"3|...","refresh_token":"4|...","token_type":"Bearer","expires_in":1800}
     * @response 401 {"message":"Unauthenticated."}
     * @response 409 {"message":"This refresh token was just used. Retry with the newest tokens."}
     */
    public function refresh(Request $request): JsonResponse
    {
        $plainToken = $request->bearerToken();
        $token = $plainToken ? PersonalAccessToken::findToken($plainToken) : null;

        if ($token === null || ! $token->tokenable instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        /** @var User $user */
        $user = $token->tokenable;

        if ($token->name === self::CONSUMED_REFRESH_TOKEN) {
            return $this->handleReusedRefreshToken($token, $user);
        }

        if ($token->name !== 'refresh'
            || ! in_array('refresh', (array) $token->abilities, true)
            || ($token->expires_at !== null && $token->expires_at->isPast())
            || $user->is_active === false) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Consume the token with a single conditional UPDATE: of two simultaneous requests only
        // one changes the row. expires_at doubles as the moment of consumption.
        $consumed = PersonalAccessToken::query()
            ->whereKey($token->getKey())
            ->where('name', 'refresh')
            ->update(['name' => self::CONSUMED_REFRESH_TOKEN, 'expires_at' => now()]);

        if ($consumed === 0) {
            return $this->handleReusedRefreshToken($token->fresh() ?? $token, $user);
        }

        $user->tokens()->where('name', 'access')->delete();

        return response()->json($this->issueTokens($user));
    }

    private function handleReusedRefreshToken(PersonalAccessToken $token, User $user): JsonResponse
    {
        $consumedAt = $token->expires_at;

        if ($consumedAt !== null && $consumedAt->greaterThan(now()->subSeconds(self::REFRESH_GRACE_SECONDS))) {
            return response()->json(['message' => 'This refresh token was just used. Retry with the newest tokens.'], 409);
        }

        // A consumed token presented after the grace window: assume it was copied and revoke
        // every token of the user (each login already keeps a single session).
        $user->tokens()->delete();

        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    /**
     * Send a password reset link to the given email address.
     *
     * @group Authentication
     * @unauthenticated
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->validated());

        return response()->json([
            'message' => 'If your email exists in our system, you will receive a password reset link shortly.',
        ]);
    }

    /**
     * Reset password using a valid broker token.
     *
     * @group Authentication
     * @unauthenticated
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->validated(),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
            ], 422);
        }

        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }

    /**
     * Return the authenticated user profile for SPA bootstrapping.
     *
     * @group Authentication
     * @authenticated
     */
    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json([
            'user' => $user->toFrontendPayload(),
        ]);
    }

    /**
     * Update the authenticated user profile.
     *
     * @group Authentication
     * @authenticated
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->userService->updateProfile($user, $request->validated());

        return response()->json([
            'user' => $user->fresh()->toFrontendPayload(),
        ]);
    }

    /**
     * Logout user and invalidate all tokens.
     *
     * @group Authentication
     * @authenticated
     * @response 200 {"message":"Logged out."}
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function issueTokens(User $user): array
    {
        $access = $user->createToken('access', ['*'], now()->addMinutes(30));
        $refresh = $user->createToken('refresh', ['refresh'], now()->addDays(7));

        return [
            'access_token' => $access->plainTextToken,
            'refresh_token' => $refresh->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => 1800,
            'user' => $user->toFrontendPayload(),
        ];
    }
}
