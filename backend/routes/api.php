<?php

use App\Http\Controllers\Api\Admin\FailedJobController;
use App\Http\Controllers\Api\Admin\MaintenanceTestController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\WorkflowController;
use App\Http\Controllers\Api\AdminRequestController;
use App\Http\Controllers\Api\ApprovalsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['auth:api', 'role:admin,api'])->prefix('admin')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
    Route::patch('/users/{id}', [UserController::class, 'update']);
    Route::patch('/users/{id}/toggle-active', [UserController::class, 'toggleActive']);

    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']);

    Route::get('/workflows', [WorkflowController::class, 'index']);
    Route::get('/workflows/{id}', [WorkflowController::class, 'show']);
    Route::post('/workflows', [WorkflowController::class, 'store']);
    Route::patch('/workflows/{id}', [WorkflowController::class, 'update']);

    Route::get('/failed-jobs', [FailedJobController::class, 'index']);
    Route::post('/failed-jobs/{uuid}/retry', [FailedJobController::class, 'retry']);
    Route::post('/maintenance/stuck-workflows/check', [MaintenanceTestController::class, 'dispatch']);
});

Route::middleware('auth:api')->prefix('requests')->group(function () {
    Route::get('/workflow-types', [RequestController::class, 'workflowTypes']);
    Route::get('/', [RequestController::class, 'index']);
    Route::post('/', [RequestController::class, 'store']);
    Route::get('/{id}', [RequestController::class, 'show']);
    Route::get('/{id}/pending', [RequestController::class, 'pending']);
    Route::post('/{id}/steps/{stepId}/action', [ApprovalsController::class, 'action']);
    Route::post('/{id}/admin/retry', [AdminRequestController::class, 'retry'])
        ->middleware(['role:admin,api']);
});

Route::middleware('auth:api')->prefix('approvals')->group(function () {
    Route::get('/', [ApprovalsController::class, 'index']);
});

Route::middleware(['auth:api', 'role:admin,api'])->prefix('reports')->group(function () {
    Route::get('/requests', [ReportController::class, 'requests']);
});