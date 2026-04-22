<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AdminRetryWorkflow;
use App\Jobs\RebuildWorkflowProjection;
use App\Models\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;

class AdminRequestController extends Controller
{
    public function retry(HttpRequest $httpRequest, int $id): JsonResponse
    {
        $httpRequest->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $request = Request::query()->findOrFail($id);

        AdminRetryWorkflow::dispatch(
            $request->id,
            Auth::id()
        );

        RebuildWorkflowProjection::dispatch($request->id);

        return response()->json([
            'message' => 'Retry job queued successfully.',
            'data' => [
                'request_id' => $request->id,
                'queued' => true,
            ],
        ], 202);
    }
}