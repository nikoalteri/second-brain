<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
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

        $access  = $user->createToken('access', ['*'], now()->addMinutes(30));
        $refresh = $user->createToken('refresh', ['refresh'], now()->addDays(7));

        return response()->json([
            'access_token'  => $access->plainTextToken,
            'refresh_token' => $refresh->plainTextToken,
            'token_type'    => 'Bearer',
            'expires_in'    => 1800,
        ]);
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
}
