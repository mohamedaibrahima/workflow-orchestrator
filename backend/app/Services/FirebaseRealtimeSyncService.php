<?php

namespace App\Services;

use App\Models\Request;
use Illuminate\Support\Facades\Http;

class FirebaseRealtimeSyncService
{
    public function syncRequest(int $requestId): void
    {
        $request = Request::query()
            ->with([
                'requester:id,name,email',
                'workflowType:id,name,code,description',
                'currentStep.role:id,name',
                'steps.role:id,name',
                'steps.actor:id,name,email',
                'steps.assignments.user:id,name,email',
                'steps.assignments.role:id,name',
                'steps.actions.user:id,name,email',
                'actions.user:id,name,email',
                'auditEvents',
            ])
            ->find($requestId);

        if (! $request) {
            return;
        }

        $databaseUrl = rtrim((string) config('services.firebase.database_url'), '/');
        $databaseSecret = (string) config('services.firebase.database_secret');

        if (
            app()->environment('testing') ||
            $databaseUrl === '' ||
            $databaseSecret === '' ||
            str_contains($databaseUrl, 'YOUR-')
        ) {
            return;
        }

        $detailsPayload = [
            'id' => $request->id,
            'status' => $request->status,
            'created_at' => $request->created_at?->toISOString(),
            'payload' => $request->payload,
            'workflow_type' => $request->workflowType ? [
                'id' => $request->workflowType->id,
                'name' => $request->workflowType->name,
                'code' => $request->workflowType->code,
                'description' => $request->workflowType->description,
            ] : null,
            'steps' => $request->steps->map(function ($step) {
                return [
                    'id' => $step->id,
                    'name' => $step->name,
                    'step_key' => $step->step_key,
                    'status' => $step->status,
                    'sequence_order' => $step->sequence_order,
                    'execution_type' => $step->execution_type,
                    'approval_mode' => $step->approval_mode,
                    'parallel_group' => $step->parallel_group,
                    'acted_at' => $step->acted_at?->toISOString(),
                    'comment' => $step->comment,
                    'role' => $step->role ? [
                        'id' => $step->role->id,
                        'name' => $step->role->name,
                    ] : null,
                    'actor' => $step->actor ? [
                        'id' => $step->actor->id,
                        'name' => $step->actor->name,
                        'email' => $step->actor->email,
                    ] : null,
                    'assignments' => $step->assignments->map(function ($assignment) {
                        return [
                            'id' => $assignment->id,
                            'user_id' => $assignment->user_id,
                            'status' => $assignment->status,
                            'acted_at' => $assignment->acted_at?->toISOString(),
                            'user' => $assignment->user ? [
                                'id' => $assignment->user->id,
                                'name' => $assignment->user->name,
                                'email' => $assignment->user->email,
                            ] : null,
                            'role' => $assignment->role ? [
                                'id' => $assignment->role->id,
                                'name' => $assignment->role->name,
                            ] : null,
                        ];
                    })->values()->all(),
                    'actions' => $step->actions->map(function ($action) {
                        return [
                            'id' => $action->id,
                            'action' => $action->action,
                            'comment' => $action->comment,
                            'is_effective' => $action->is_effective,
                            'metadata' => $action->metadata,
                            'acted_at' => $action->acted_at?->toISOString(),
                            'created_at' => $action->created_at?->toISOString(),
                            'user' => $action->user ? [
                                'id' => $action->user->id,
                                'name' => $action->user->name,
                                'email' => $action->user->email,
                            ] : null,
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
            'actions' => $request->actions->map(function ($action) {
                return [
                    'id' => $action->id,
                    'action' => $action->action,
                    'comment' => $action->comment,
                    'is_effective' => $action->is_effective,
                    'metadata' => $action->metadata,
                    'acted_at' => $action->acted_at?->toISOString(),
                    'created_at' => $action->created_at?->toISOString(),
                    'user' => $action->user ? [
                        'id' => $action->user->id,
                        'name' => $action->user->name,
                        'email' => $action->user->email,
                    ] : null,
                ];
            })->values()->all(),
            'audit_events' => $request->auditEvents->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'created_at' => $event->created_at?->toISOString(),
                    'payload' => $event->payload,
                ];
            })->values()->all(),
        ];

        $summaryPayload = [
            'id' => $request->id,
            'status' => $request->status,
            'workflow_type_id' => $request->workflow_type_id,
            'workflow_type_name' => $request->workflowType?->name,
            'workflow_name' => $request->workflowType?->name,
            'created_at' => $request->created_at?->toISOString(),
            'current_step_name' => $request->currentStep?->name,
            'payload' => $request->payload,
            'workflow_type' => $request->workflowType ? [
                'id' => $request->workflowType->id,
                'name' => $request->workflowType->name,
                'code' => $request->workflowType->code,
                'description' => $request->workflowType->description,
            ] : null,
        ];

        try {
            Http::retry(2, 200)->put(
                $databaseUrl . '/requests/' . $request->id . '.json?auth=' . $databaseSecret,
                $detailsPayload
            );

            Http::retry(2, 200)->put(
                $databaseUrl . '/request_summaries/' . $request->id . '.json?auth=' . $databaseSecret,
                $summaryPayload
            );
        } catch (\Throwable $e) {
            report($e);
            return;
        }

        $pendingApprovalSteps = $request->steps
            ->filter(function ($step) {
                return in_array($step->status, ['pending', 'in_progress'], true)
                    && $step->assignments->contains(function ($assignment) {
                        return $assignment->status === 'pending';
                    });
            })
            ->values();

        foreach ($pendingApprovalSteps as $step) {
            foreach ($step->assignments->where('status', 'pending') as $assignment) {
                if (! $assignment->user_id) {
                    continue;
                }

                $approvalPayload = [
                    'id' => $request->id,
                    'request_id' => $request->id,
                    'status' => $request->status,
                    'requester_name' => $request->requester?->name,
                    'workflow_type' => $request->workflowType ? [
                        'id' => $request->workflowType->id,
                        'name' => $request->workflowType->name,
                    ] : null,
                    'workflow_name' => $request->workflowType?->name,
                    'current_step' => [
                        'id' => $step->id,
                        'name' => $step->name,
                        'step_key' => $step->step_key,
                        'status' => $step->status,
                        'sequence_order' => $step->sequence_order,
                        'execution_type' => $step->execution_type,
                        'approval_mode' => $step->approval_mode,
                        'parallel_group' => $step->parallel_group,
                        'role' => $step->role ? [
                            'id' => $step->role->id,
                            'name' => $step->role->name,
                        ] : null,
                        'assignments' => $step->assignments
                            ->where('user_id', $assignment->user_id)
                            ->map(function ($currentAssignment) {
                                return [
                                    'id' => $currentAssignment->id,
                                    'user_id' => $currentAssignment->user_id,
                                    'status' => $currentAssignment->status,
                                ];
                            })
                            ->values()
                            ->all(),
                    ],
                    'steps' => [[
                        'id' => $step->id,
                        'name' => $step->name,
                        'step_key' => $step->step_key,
                        'status' => $step->status,
                        'sequence_order' => $step->sequence_order,
                        'execution_type' => $step->execution_type,
                        'approval_mode' => $step->approval_mode,
                        'parallel_group' => $step->parallel_group,
                        'role' => $step->role ? [
                            'id' => $step->role->id,
                            'name' => $step->role->name,
                        ] : null,
                        'assignments' => $step->assignments
                            ->where('user_id', $assignment->user_id)
                            ->map(function ($currentAssignment) {
                                return [
                                    'id' => $currentAssignment->id,
                                    'user_id' => $currentAssignment->user_id,
                                    'status' => $currentAssignment->status,
                                ];
                            })
                            ->values()
                            ->all(),
                    ]],
                    'payload' => $request->payload,
                    'created_at' => $request->created_at?->toISOString(),
                ];

                try {
                    Http::retry(2, 200)->put(
                        $databaseUrl . '/approvals/' . $assignment->user_id . '/' . $request->id . '.json?auth=' . $databaseSecret,
                        $approvalPayload
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }
    }
}