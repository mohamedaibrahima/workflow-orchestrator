<?php

namespace Tests\Feature;

use App\Jobs\OrchestrateWorkflow;
use App\Jobs\ProcessStepApproval;
use App\Models\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Support\WorkflowTestHelpers;
use Tests\TestCase;

class ApprovalModesTest extends TestCase
{
    use RefreshDatabase, WorkflowTestHelpers;

    public function test_approval_mode_any_first_approver_wins(): void
    {
        $employee = $this->createUserWithRole('Employee Any', 'employee.any@example.com', 'employee');
        $managerOne = $this->createUserWithRole('Manager Any 1', 'manager.any1@example.com', 'manager');
        $managerTwo = $this->createUserWithRole('Manager Any 2', 'manager.any2@example.com', 'manager');

        $workflowType = $this->createWorkflowTypeWithSteps('Any Approval Flow', [[
            'name' => 'Manager Approval',
            'role_id' => $managerOne->roles->first()->id,
            'sequence_order' => 1,
            'approval_mode' => 'any',
        ]]);

        $this->actingAs($employee, 'api');
        $response = $this->postJson('/api/requests', [
            'workflow_type_id' => $workflowType->id,
            'payload' => ['subject' => 'any test'],
        ])->assertCreated();

        $request = Request::with('steps.assignments')->findOrFail($response->json('data.id'));
        OrchestrateWorkflow::dispatchSync($request->id);

        $step = $request->steps->first();

        ProcessStepApproval::dispatchSync($request->id, $step->id, 'approve', 'approved by first manager', $managerOne->id, 'any-approve-1');
        OrchestrateWorkflow::dispatchSync($request->id);

        $step->refresh();
        $this->assertEquals('approved', $step->status);
        $this->assertEquals($managerOne->id, $step->acted_by);

        $this->assertDatabaseHas('request_step_assignments', [
            'request_step_id' => $step->id,
            'user_id' => $managerTwo->id,
            'status' => 'skipped',
        ]);
    }

    public function test_approval_mode_all_requires_all_snapshot_assignees(): void
    {
        $employee = $this->createUserWithRole('Employee All', 'employee.all@example.com', 'employee');
        $financeOne = $this->createUserWithRole('Finance All 1', 'finance.all1@example.com', 'finance');
        $financeTwo = $this->createUserWithRole('Finance All 2', 'finance.all2@example.com', 'finance');

        $workflowType = $this->createWorkflowTypeWithSteps('All Approval Flow', [[
            'name' => 'Finance Approval',
            'role_id' => $financeOne->roles->first()->id,
            'sequence_order' => 1,
            'approval_mode' => 'all',
        ]]);

        $this->actingAs($employee, 'api');
        $response = $this->postJson('/api/requests', [
            'workflow_type_id' => $workflowType->id,
            'payload' => ['subject' => 'all test'],
        ])->assertCreated();

        $request = Request::with('steps.assignments')->findOrFail($response->json('data.id'));
        OrchestrateWorkflow::dispatchSync($request->id);

        $step = $request->steps->first();

        ProcessStepApproval::dispatchSync($request->id, $step->id, 'approve', 'first finance approved', $financeOne->id, 'all-approve-1');
        $step->refresh();
        $this->assertEquals('pending', $step->status);

        ProcessStepApproval::dispatchSync($request->id, $step->id, 'approve', 'second finance approved', $financeTwo->id, 'all-approve-2');
        OrchestrateWorkflow::dispatchSync($request->id);

        $step->refresh();
        $this->assertEquals('approved', $step->status);
    }
}