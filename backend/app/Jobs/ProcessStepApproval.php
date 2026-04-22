<?php

namespace App\Jobs;

use App\Models\Request;
use App\Models\RequestStep;
use App\Models\StepAction;
use App\Services\FirebaseRealtimeSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessStepApproval implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $requestId;
    public int $requestStepId;
    public string $action;
    public ?string $comment;
    public int $userId;
    public string $idempotencyKey;
    public int $tries = 5;

    public function __construct(
        int $requestId,
        int $requestStepId,
        string $action,
        ?string $comment,
        int $userId,
        string $idempotencyKey
    ) {
        $this->requestId = $requestId;
        $this->requestStepId = $requestStepId;
        $this->action = $action;
        $this->comment = $comment;
        $this->userId = $userId;
        $this->idempotencyKey = $idempotencyKey;
        $this->onQueue('steps');
    }

    public function handle(FirebaseRealtimeSyncService $firebaseRealtimeSyncService): void
    {
        $shouldOrchestrate = false;

        DB::transaction(function () use (&$shouldOrchestrate): void {
            $existingAction = StepAction::query()
                ->where('idempotency_key', $this->idempotencyKey)
                ->first();

            if ($existingAction) {
                return;
            }

            $request = Request::query()
                ->lockForUpdate()
                ->find($this->requestId);

            $step = RequestStep::query()
                ->with('assignments')
                ->lockForUpdate()
                ->find($this->requestStepId);

            if (! $request || ! $step || $step->request_id !== $request->id) {
                return;
            }

            if (in_array($step->status, ['approved', 'rejected', 'skipped'], true)) {
                StepAction::query()->create([
                    'request_id' => $request->id,
                    'request_step_id' => $step->id,
                    'user_id' => $this->userId,
                    'action' => $this->action,
                    'comment' => $this->comment,
                    'idempotency_key' => $this->idempotencyKey,
                    'is_effective' => false,
                    'metadata' => ['reason' => 'step_already_closed'],
                    'acted_at' => now(),
                ]);

                return;
            }

            $assignment = $step->assignments()
                ->where('user_id', $this->userId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $assignment) {
                StepAction::query()->create([
                    'request_id' => $request->id,
                    'request_step_id' => $step->id,
                    'user_id' => $this->userId,
                    'action' => $this->action,
                    'comment' => $this->comment,
                    'idempotency_key' => $this->idempotencyKey,
                    'is_effective' => false,
                    'metadata' => ['reason' => 'no_pending_assignment'],
                    'acted_at' => now(),
                ]);

                return;
            }

            StepAction::query()->create([
                'request_id' => $request->id,
                'request_step_id' => $step->id,
                'user_id' => $this->userId,
                'action' => $this->action,
                'comment' => $this->comment,
                'idempotency_key' => $this->idempotencyKey,
                'is_effective' => true,
                'metadata' => null,
                'acted_at' => now(),
            ]);

            if ($this->action === 'reject') {
                $assignment->update([
                    'status' => 'rejected',
                    'acted_at' => now(),
                ]);

                $step->assignments()
                    ->where('id', '!=', $assignment->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'skipped',
                        'acted_at' => now(),
                    ]);

                $step->update([
                    'status' => 'rejected',
                    'acted_at' => now(),
                    'acted_by' => $this->userId,
                    'comment' => $this->comment,
                ]);

                $request->update([
                    'status' => 'rejected',
                    'current_step_id' => null,
                    'rejected_step_id' => $step->id,
                ]);

                return;
            }

            $assignment->update([
                'status' => 'approved',
                'acted_at' => now(),
            ]);

            if ($step->approval_mode === 'any') {
                $step->assignments()
                    ->where('id', '!=', $assignment->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'skipped',
                        'acted_at' => now(),
                    ]);

                $step->update([
                    'status' => 'approved',
                    'acted_at' => now(),
                    'acted_by' => $this->userId,
                    'comment' => $this->comment,
                ]);

                $shouldOrchestrate = true;

                return;
            }

            $pendingAssignmentsCount = $step->assignments()
                ->where('status', 'pending')
                ->count();

            if ($pendingAssignmentsCount === 0) {
                $step->update([
                    'status' => 'approved',
                    'acted_at' => now(),
                    'acted_by' => $this->userId,
                    'comment' => $this->comment,
                ]);

                $shouldOrchestrate = true;
            } else {
                $step->update([
                    'status' => 'pending',
                ]);
            }
        });

        $firebaseRealtimeSyncService->syncRequest($this->requestId);

        if ($shouldOrchestrate) {
            OrchestrateWorkflow::dispatch($this->requestId);
        }
    }
}