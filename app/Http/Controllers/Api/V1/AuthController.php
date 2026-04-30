<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @group Authentication
 *
 * Endpoints for user authentication and token management.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
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
     * Refresh an expired access token using a valid refresh token.
     *
     * @group Authentication
     * @authenticated
     * @response 200 {"access_token":"3|...","token_type":"Bearer","expires_in":1800}
     * @response 401 {"message":"Unauthenticated."}
     */
    public function refresh(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Revoke only access tokens; keep the refresh token alive
        $user->tokens()->where('name', 'access')->delete();

        $access = $user->createToken('access', ['*'], now()->addMinutes(30));

        return response()->json([
            'access_token' => $access->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_in'   => 1800,
            'user'         => $user->toFrontendPayload(),
        ]);
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
