<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\MaintenanceCheckStuckWorkflows;
use Illuminate\Http\JsonResponse;

class MaintenanceTestController extends Controller
{
    public function dispatch(): JsonResponse
    {
        MaintenanceCheckStuckWorkflows::dispatch();

        return response()->json([
            'message' => 'Maintenance job queued successfully.',
        ], 202);
    }
}