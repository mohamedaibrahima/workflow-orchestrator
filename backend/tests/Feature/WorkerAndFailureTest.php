<?php

namespace Tests\Feature;

use App\Jobs\AdminRetryWorkflow;
use App\Jobs\MaintenanceCheckStuckWorkflows;
use App\Jobs\OrchestrateWorkflow;
use App\Jobs\RebuildWorkflowProjection;
use App\Jobs\RunWorkflowCompensation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Support\WorkflowTestHelpers;
use Tests\TestCase;
use Throwable;

class WorkerAndFailureTest extends TestCase
{
    use RefreshDatabase, WorkflowTestHelpers;

    public function test_admin_retry_endpoint_should_queue_retry_job(): void
    {
        Queue::fake();

        $employee = $this->createUserWithRole('Employee Worker', 'employee-worker@example.com', 'employee');
        $admin = $this->createUserWithRole('Admin Worker', 'admin-worker@example.com', 'admin');

        $workflowType = $this->createWorkflowTypeWithSteps('Retry Workflow', [
            [
                'name' => 'Manager Approval',
                'role_id' => $admin->roles->first()->id,
                'sequence_order' => 1,
                'approval_mode' => 'any',
            ],
        ]);

        $request = $this->createRequestForWorkflow($employee, $workflowType, [
            'amount' => 500,
            'reason' => 'test retry',
        ]);

        $this->actingAs($admin, 'api');

        try {
            $response = $this->postJson("/api/requests/{$request->id}/admin/retry", [
                'reason' => 'retry requested by admin',
            ]);
        } catch (Throwable $e) {
            $this->fail(
                'Admin retry endpoint threw exception: '
                . $e::class
                . ' | message: '
                . $e->getMessage()
            );
        }

        $response->assertAccepted()
            ->assertJsonPath('data.request_id', $request->id)
            ->assertJsonPath('data.queued', true);

        Queue::assertPushed(AdminRetryWorkflow::class);
        Queue::assertPushed(RebuildWorkflowProjection::class);
    }

    public function test_worker_jobs_can_be_asserted_as_dispatched(): void
    {
        Queue::fake();

        MaintenanceCheckStuckWorkflows::dispatch();
        RunWorkflowCompensation::dispatch(1);
        RebuildWorkflowProjection::dispatch(1);
        OrchestrateWorkflow::dispatch(1);

        Queue::assertPushed(MaintenanceCheckStuckWorkflows::class);
        Queue::assertPushed(RunWorkflowCompensation::class);
        Queue::assertPushed(RebuildWorkflowProjection::class);
        Queue::assertPushed(OrchestrateWorkflow::class);
    }
}