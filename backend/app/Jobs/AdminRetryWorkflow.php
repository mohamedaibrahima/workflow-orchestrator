<?php

namespace App\Jobs;

use App\Models\Request;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AdminRetryWorkflow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $requestId;
    public int $adminUserId;
    public int $tries = 3;

    public function __construct(int $requestId, string|int $adminUserId)
    {
        $this->requestId = $requestId;
        $this->adminUserId = (int) $adminUserId;
        $this->onQueue('admin');
    }

    public function handle(): void
    {
        $request = Request::query()->find($this->requestId);

        if (! $request) {
            return;
        }

        if (in_array($request->status, ['completed', 'cancelled'], true)) {
            return;
        }

        OrchestrateWorkflow::dispatchSync($request->id);
    }
}