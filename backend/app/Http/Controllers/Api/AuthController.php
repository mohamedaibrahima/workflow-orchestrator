<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'is_active' => true,
        ]);

        $token = auth('api')->login($user);

        return $this->respondWithToken(
            $token,
            $user->load('roles')
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        $token = auth('api')->attempt($credentials);

        if (! $token) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        /** @var \App\Models\User $user */
        $user = auth('api')->user();

        if (! $user->is_active) {
            auth('api')->logout();

            return response()->json([
                'message' => 'This account is inactive',
            ], 403);
        }

        return $this->respondWithToken(
            $token,
            $user->load('roles')
        );
    }

    public function me(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth('api')->user();

        return response()->json([
            'user' => $user->load('roles'),
        ]);
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    protected function respondWithToken($token, User $user): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => 3600,
            'user' => $user,
        ]);
    }
}