<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\OrchestrateWorkflow;
use App\Models\Request;
use App\Models\User;
use App\Models\WorkflowType;
use App\Support\StoresAuditEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RequestController extends Controller
{
    use StoresAuditEvents;

    public function workflowTypes(): JsonResponse
    {
        $workflowTypes = WorkflowType::query()
            ->where('is_active', true)
            ->with(['steps' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('sequence_order')
                    ->orderBy('id')
                    ->with('role');
            }])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $workflowTypes,
        ]);
    }

    public function index(): JsonResponse
    {
        $requests = Request::query()
            ->where('requester_id', Auth::id())
            ->with(['workflowType', 'steps.role', 'steps.assignments.user', 'actions', 'auditEvents'])
            ->latest()
            ->get();

        return response()->json([
            'data' => $requests,
        ]);
    }

    public function store(HttpRequest $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'workflow_type_id' => ['required', 'integer', 'exists:workflow_types,id'],
            'payload' => ['required', 'array'],
        ]);

        $workflowType = WorkflowType::query()
            ->with(['steps' => function ($query) {
                $query->where('is_active', true)->orderBy('sequence_order')->orderBy('id');
            }])
            ->where('is_active', true)
            ->findOrFail($validated['workflow_type_id']);

        if ($workflowType->steps->isEmpty()) {
            return response()->json([
                'message' => 'The selected workflow type has no active steps.',
            ], 422);
        }

        $request = DB::transaction(function () use ($validated, $workflowType) {
            $firstStep = $workflowType->steps->first();

            $request = Request::create([
                'requester_id' => Auth::id(),
                'workflow_type_id' => $workflowType->id,
                'current_step_id' => null,
                'rejected_step_id' => null,
                'status' => 'pending',
                'payload' => $validated['payload'],
                'approved_steps' => [],
            ]);

            foreach ($workflowType->steps as $step) {
                $requestStep = $request->steps()->create([
                    'workflow_step_id' => $step->id,
                    'step_key' => $step->step_key,
                    'name' => $step->name,
                    'role_id' => $step->role_id,
                    'sequence_order' => $step->sequence_order,
                    'execution_type' => $step->execution_type,
                    'approval_mode' => $step->approval_mode,
                    'parallel_group' => $step->parallel_group,
                    'status' => 'pending',
                ]);

                $approverIds = User::role($step->role->name)->pluck('id');

                foreach ($approverIds as $approverId) {
                    $request->assignments()->create([
                        'request_step_id' => $requestStep->id,
                        'role_id' => $step->role_id,
                        'user_id' => $approverId,
                        'status' => 'pending',
                    ]);
                }

                $this->storeAuditEvent(
                    requestId: $request->id,
                    requestStepId: $requestStep->id,
                    userId: Auth::id(),
                    eventType: 'request_step_snapshotted',
                    eventKey: 'request_step_snapshotted:' . $request->id . ':' . $requestStep->id,
                    payload: [
                        'workflow_step_id' => $step->id,
                        'step_key' => $step->step_key,
                        'role_id' => $step->role_id,
                        'approval_mode' => $step->approval_mode,
                        'execution_type' => $step->execution_type,
                        'parallel_group' => $step->parallel_group,
                    ],
                );
            }

            if ($firstStep) {
                $firstRequestStep = $request->steps()
                    ->where('sequence_order', $firstStep->sequence_order)
                    ->orderBy('id')
                    ->first();

                $request->update([
                    'current_step_id' => $firstRequestStep?->id,
                ]);
            }

            $this->storeAuditEvent(
                requestId: $request->id,
                requestStepId: null,
                userId: Auth::id(),
                eventType: 'request_created',
                eventKey: 'request_created:' . $request->id,
                payload: [
                    'workflow_type_id' => $workflowType->id,
                    'status' => 'pending',
                ],
            );

            return $request->load(['workflowType', 'steps.role', 'steps.assignments.user', 'actions', 'auditEvents']);
        });

        OrchestrateWorkflow::dispatch($request->id);

        return response()->json([
            'message' => 'Request created successfully',
            'data' => $request,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $request = Request::query()
            ->where('requester_id', Auth::id())
            ->with(['workflowType', 'steps.role', 'steps.actor', 'steps.actions.user', 'steps.assignments.user', 'actions', 'auditEvents'])
            ->findOrFail($id);

        return response()->json([
            'data' => $request,
        ]);
    }

    public function pending(int $id): JsonResponse
    {
        $request = Request::query()
            ->where('requester_id', Auth::id())
            ->with(['steps.role', 'steps.assignments.user'])
            ->findOrFail($id);

        return response()->json([
            'data' => $request->steps()
                ->where('status', 'pending')
                ->get(),
        ]);
    }
}