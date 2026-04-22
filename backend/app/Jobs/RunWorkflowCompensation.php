<?php

namespace App\Jobs;

use App\Models\Request;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class RunWorkflowCompensation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $requestId;
    public int $tries = 3;

    public function __construct(int $requestId)
    {
        $this->requestId = $requestId;
        $this->onQueue('compensation');
    }

    public function handle(): void
    {
        DB::transaction(function (): void {
            $request = Request::query()
                ->with(['steps.assignments'])
                ->lockForUpdate()
                ->find($this->requestId);

            if (! $request) {
                return;
            }

            foreach ($request->steps as $step) {
                if (in_array($step->status, ['approved', 'rejected'], true)) {
                    continue;
                }

                $step->assignments()
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->update([
                        'status' => 'skipped',
                        'acted_at' => now(),
                    ]);

                $step->update([
                    'status' => 'skipped',
                    'comment' => 'Workflow compensation executed',
                ]);
            }

            $request->update([
                'status' => 'cancelled',
                'current_step_id' => null,
            ]);
        });
    }
}