<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class FailedJobController extends Controller
{
    public function index(): JsonResponse
    {
        $failedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->get();

        return response()->json([
            'data' => $failedJobs,
        ]);
    }

    public function retry(Request $request, string $uuid): JsonResponse
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return response()->json([
            'message' => 'Failed job retried successfully.',
            'uuid' => $uuid,
        ]);
    }
}