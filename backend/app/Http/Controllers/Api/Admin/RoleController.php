<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Models\WorkflowStep;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->where('guard_name', 'api')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $roles,
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::create([
            'name' => $request->input('name'),
            'guard_name' => 'api',
        ]);

        return response()->json([
            'message' => 'Role created successfully',
            'data' => $role,
        ], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::query()
            ->where('guard_name', 'api')
            ->findOrFail($id);

        $isUsedInWorkflow = WorkflowStep::query()
            ->where('role_id', $role->id)
            ->exists();

        if ($isUsedInWorkflow) {
            return response()->json([
                'message' => 'This role cannot be deleted because it is referenced by a workflow definition.',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }
}