<?php

namespace App\Jobs;

use App\Models\Request;
use App\Services\FirebaseRealtimeSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class OrchestrateWorkflow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $requestId;

    public int $tries = 5;

    public function __construct(int $requestId)
    {
        $this->requestId = $requestId;
        $this->onQueue('orchestration');
    }

    public function handle(FirebaseRealtimeSyncService $firebaseRealtimeSyncService): void
    {
        DB::transaction(function (): void {
            $request = Request::query()
                ->with(['steps.assignments'])
                ->lockForUpdate()
                ->find($this->requestId);

            if (! $request) {
                return;
            }

            $steps = $request->steps
                ->sortBy([
                    ['sequence_order', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();

            if ($steps->isEmpty()) {
                $request->update([
                    'status' => 'completed',
                    'current_step_id' => null,
                    'rejected_step_id' => null,
                ]);

                return;
            }

            foreach ($steps->groupBy('sequence_order') as $sequenceSteps) {
                $sequenceSteps = $sequenceSteps->sortBy('id')->values();

                $anyRejected = $sequenceSteps->contains(function ($step) {
                    return $step->status === 'rejected';
                });

                if ($anyRejected) {
                    $rejectedStep = $sequenceSteps->firstWhere('status', 'rejected');

                    $request->update([
                        'status' => 'rejected',
                        'current_step_id' => null,
                        'rejected_step_id' => $rejectedStep?->id,
                    ]);

                    return;
                }

                $allApproved = $sequenceSteps->every(function ($step) {
                    return $step->status === 'approved';
                });

                if ($allApproved) {
                    continue;
                }

                $activeSteps = $sequenceSteps->filter(function ($step) {
                    return in_array($step->status, ['pending', 'in_progress'], true);
                })->values();

                if ($activeSteps->isNotEmpty()) {
                    foreach ($activeSteps as $step) {
                        if ($step->status === 'pending') {
                            $step->update([
                                'status' => 'in_progress',
                            ]);
                        }
                    }

                    $currentStep = $activeSteps->first();

                    $request->update([
                        'status' => 'in_progress',
                        'current_step_id' => $currentStep?->id,
                        'rejected_step_id' => null,
                    ]);

                    return;
                }

                $request->update([
                    'status' => 'in_progress',
                    'current_step_id' => null,
                    'rejected_step_id' => null,
                ]);

                return;
            }

            $request->update([
                'status' => 'completed',
                'current_step_id' => null,
                'rejected_step_id' => null,
            ]);
        });

        $firebaseRealtimeSyncService->syncRequest($this->requestId);
    }
}