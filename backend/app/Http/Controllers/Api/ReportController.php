<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Request;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function requests(): JsonResponse
    {
        $requests = Request::query()
            ->with(['requester', 'workflowType'])
            ->latest()
            ->get();

        $summary = [
            'total' => $requests->count(),
            'pending' => $requests->where('status', 'pending')->count(),
            'in_progress' => $requests->where('status', 'in_progress')->count(),
            'completed' => $requests->where('status', 'completed')->count(),
            'rejected' => $requests->where('status', 'rejected')->count(),
        ];

        return response()->json([
            'summary' => $summary,
            'data' => $requests,
        ]);
    }
}