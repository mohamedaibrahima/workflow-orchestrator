<?php

namespace App\Jobs;

use App\Models\Request;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RebuildWorkflowProjection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $requestId;
    public int $tries = 3;

    public function __construct(int $requestId)
    {
        $this->requestId = $requestId;
        $this->onQueue('projections');
    }

    public function handle(): void
    {
        $request = Request::query()
            ->with(['steps.assignments', 'actions'])
            ->find($this->requestId);

        if (! $request) {
            return;
        }

        $steps = $request->steps->sortBy(['sequence_order', 'id'])->values();

        $currentStep = $steps->first(function ($step) {
            return in_array($step->status, ['pending', 'in_progress'], true);
        });

        $request->update([
            'current_step_id' => $currentStep?->id,
        ]);
    }
}