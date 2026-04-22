<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWorkflowRequest;
use App\Http\Requests\Admin\UpdateWorkflowRequest;
use App\Models\WorkflowType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkflowController extends Controller
{
    public function index(): JsonResponse
    {
        $workflows = WorkflowType::query()
            ->withCount('steps')
            ->with('steps.role')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $workflows,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $workflow = WorkflowType::query()
            ->with('steps.role')
            ->findOrFail($id);

        return response()->json([
            'data' => $workflow,
        ]);
    }

    public function store(StoreWorkflowRequest $request): JsonResponse
    {
        $workflowType = DB::transaction(function () use ($request) {
            $code = $request->input('code');

            if (blank($code)) {
                $code = Str::slug($request->input('name'));
            }

            $workflowType = WorkflowType::create([
                'name' => $request->input('name'),
                'code' => $code,
                'description' => $request->input('description'),
                'is_active' => $request->boolean('is_active', true),
            ]);

            foreach ($request->input('steps') as $step) {
                $workflowType->steps()->create([
                    'step_key' => $step['step_key'],
                    'name' => $step['name'],
                    'role_id' => $step['role_id'],
                    'sequence_order' => $step['sequence_order'],
                    'execution_type' => $step['execution_type'],
                    'parallel_group' => $step['parallel_group'] ?? null,
                    'approval_mode' => $step['approval_mode'],
                    'is_active' => $step['is_active'] ?? true,
                ]);
            }

            return $workflowType->load('steps.role');
        });

        return response()->json([
            'message' => 'Workflow type created successfully',
            'data' => $workflowType,
        ], 201);
    }

    public function update(UpdateWorkflowRequest $request, int $id): JsonResponse
    {
        $workflowType = WorkflowType::query()->findOrFail($id);

        $workflowType = DB::transaction(function () use ($workflowType, $request) {
            $code = $request->input('code');

            if (blank($code)) {
                $code = Str::slug($request->input('name'));
            }

            $workflowType->update([
                'name' => $request->input('name'),
                'code' => $code,
                'description' => $request->input('description'),
                'is_active' => $request->boolean('is_active', true),
            ]);

            $workflowType->steps()->delete();

            foreach ($request->input('steps') as $step) {
                $workflowType->steps()->create([
                    'step_key' => $step['step_key'],
                    'name' => $step['name'],
                    'role_id' => $step['role_id'],
                    'sequence_order' => $step['sequence_order'],
                    'execution_type' => $step['execution_type'],
                    'parallel_group' => $step['parallel_group'] ?? null,
                    'approval_mode' => $step['approval_mode'],
                    'is_active' => $step['is_active'] ?? true,
                ]);
            }

            return $workflowType->load('steps.role');
        });

        return response()->json([
            'message' => 'Workflow type updated successfully',
            'data' => $workflowType,
        ]);
    }
}