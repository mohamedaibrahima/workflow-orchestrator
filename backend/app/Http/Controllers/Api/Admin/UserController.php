<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->with('roles')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $users,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::query()
            ->with('roles')
            ->findOrFail($id);

        return response()->json([
            'data' => $user,
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::query()->create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => $request->input('password'),
                'is_active' => $request->boolean('is_active', true),
            ]);

            $roleIds = collect($request->input('roles'))
                ->map(fn ($roleId) => (int) $roleId)
                ->all();

            $roleNames = Role::query()
                ->where('guard_name', 'api')
                ->whereIn('id', $roleIds)
                ->pluck('name')
                ->all();

            $user->syncRoles($roleNames);

            return $user->load('roles');
        });

        return response()->json([
            'message' => 'User created successfully',
            'data' => $user,
        ], 201);
    }

    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        $user = DB::transaction(function () use ($request, $user) {
            $payload = [
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'is_active' => $request->boolean('is_active', true),
            ];

            if ($request->filled('password')) {
                $payload['password'] = $request->input('password');
            }

            $user->update($payload);

            $roleIds = collect($request->input('roles'))
                ->map(fn ($roleId) => (int) $roleId)
                ->all();

            $roleNames = Role::query()
                ->where('guard_name', 'api')
                ->whereIn('id', $roleIds)
                ->pluck('name')
                ->all();

            $user->syncRoles($roleNames);

            return $user->load('roles');
        });

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user,
        ]);
    }

    public function toggleActive(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        $user->update([
            'is_active' => ! $user->is_active,
        ]);

        return response()->json([
            'message' => $user->is_active
                ? 'User activated successfully'
                : 'User deactivated successfully',
            'data' => $user->load('roles'),
        ]);
    }
}