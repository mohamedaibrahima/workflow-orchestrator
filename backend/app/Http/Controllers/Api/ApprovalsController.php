<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessStepApproval;
use App\Models\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ApprovalsController extends Controller
{
    public function index(): JsonResponse
    {
        $authId = Auth::id();

        if (! $authId) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $requests = Request::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['workflowType', 'currentStep'])
            ->get()
            ->map(function (Request $request) use ($authId) {
                $currentStep = $request->currentStep;

                if (! $currentStep) {
                    return null;
                }

                $activeSequenceOrder = $currentStep->sequence_order;

                $activeSteps = $request->steps()
                    ->where('sequence_order', $activeSequenceOrder)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->whereHas('assignments', function ($assignmentQuery) use ($authId) {
                        $assignmentQuery->where('user_id', $authId)
                            ->where('status', 'pending');
                    })
                    ->with([
                        'assignments' => function ($assignmentQuery) use ($authId) {
                            $assignmentQuery->where('user_id', $authId)
                                ->where('status', 'pending');
                        },
                        'role',
                    ])
                    ->orderBy('sequence_order')
                    ->orderBy('id')
                    ->get();

                if ($activeSteps->isEmpty()) {
                    return null;
                }

                $request->setRelation('steps', $activeSteps);

                return $request;
            })
            ->filter()
            ->values();

        return response()->json([
            'data' => $requests,
        ]);
    }

    public function action(HttpRequest $httpRequest, int $id, int $stepId): JsonResponse
    {
        $validated = $httpRequest->validate([
            'action' => ['required', 'in:approve,reject'],
            'comment' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        $authId = Auth::id();

        if (! $authId) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $request = Request::query()
            ->with(['currentStep'])
            ->findOrFail($id);

        $currentStep = $request->currentStep;

        if (! $currentStep) {
            return response()->json([
                'message' => 'This request has no active step.',
            ], 409);
        }

        $activeSequenceOrder = $currentStep->sequence_order;

        $step = $request->steps()
            ->where('id', $stepId)
            ->where('sequence_order', $activeSequenceOrder)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('assignments', function ($query) use ($authId) {
                $query->where('user_id', $authId)
                    ->where('status', 'pending');
            })
            ->first();

        if (! $step) {
            return response()->json([
                'message' => 'You are not allowed to act on this step.',
            ], 403);
        }

        $idempotencyKey = $validated['idempotency_key'] ?? Str::uuid()->toString();

        ProcessStepApproval::dispatch(
            requestId: $request->id,
            requestStepId: $step->id,
            action: $validated['action'],
            comment: $validated['comment'] ?? null,
            userId: $authId,
            idempotencyKey: $idempotencyKey,
        );

        return response()->json([
            'message' => 'Step action accepted and queued successfully.',
            'data' => [
                'request_id' => $request->id,
                'step_id' => $step->id,
                'action' => $validated['action'],
                'idempotency_key' => $idempotencyKey,
                'queued' => true,
            ],
        ], 202);
    }
}