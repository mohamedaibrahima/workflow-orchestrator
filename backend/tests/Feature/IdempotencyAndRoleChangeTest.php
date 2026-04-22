<?php

namespace Tests\Feature;

use App\Jobs\OrchestrateWorkflow;
use App\Jobs\ProcessStepApproval;
use App\Models\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Support\WorkflowTestHelpers;
use Tests\TestCase;

class IdempotencyAndRoleChangeTest extends TestCase
{
    use RefreshDatabase, WorkflowTestHelpers;

    public function test_duplicate_idempotency_key_does_not_duplicate_action_effect(): void
    {
        $employee = $this->createUserWithRole('Employee Idem', 'employee.idem@example.com', 'employee');
        $manager = $this->createUserWithRole('Manager Idem', 'manager.idem@example.com', 'manager');

        $workflowType = $this->createWorkflowTypeWithSteps('Idempotency Flow', [[
            'name' => 'Manager Approval',
            'role_id' => $manager->roles->first()->id,
            'sequence_order' => 1,
            'approval_mode' => 'any',
        ]]);

        $this->actingAs($employee, 'api');
        $response = $this->postJson('/api/requests', [
            'workflow_type_id' => $workflowType->id,
            'payload' => ['subject' => 'idem test'],
        ])->assertCreated();

        $request = Request::with('steps')->findOrFail($response->json('data.id'));
        OrchestrateWorkflow::dispatchSync($request->id);

        $step = $request->steps->first();

        ProcessStepApproval::dispatchSync($request->id, $step->id, 'approve', 'first call', $manager->id, 'same-key-1');
        ProcessStepApproval::dispatchSync($request->id, $step->id, 'approve', 'duplicate call', $manager->id, 'same-key-1');

        $this->assertDatabaseCount('step_actions', 1);
    }

    public function test_snapshot_assignments_are_not_changed_when_role_membership_changes_later(): void
    {
        $employee = $this->createUserWithRole('Employee Snapshot', 'employee.snapshot@example.com', 'employee');
        $managerOne = $this->createUserWithRole('Manager Snapshot 1', 'manager.snapshot1@example.com', 'manager');
        $managerTwo = $this->createUserWithRole('Manager Snapshot 2', 'manager.snapshot2@example.com', 'manager');

        $workflowType = $this->createWorkflowTypeWithSteps('Snapshot Flow', [[
            'name' => 'Manager Approval',
            'role_id' => $managerOne->roles->first()->id,
            'sequence_order' => 1,
            'approval_mode' => 'all',
        ]]);

        $this->actingAs($employee, 'api');
        $response = $this->postJson('/api/requests', [
            'workflow_type_id' => $workflowType->id,
            'payload' => ['subject' => 'snapshot test'],
        ])->assertCreated();

        $request = Request::with('steps.assignments')->findOrFail($response->json('data.id'));
        $step = $request->steps->first();

        $managerTwo->removeRole('manager');
        $managerThree = $this->createUserWithRole('Manager Snapshot 3', 'manager.snapshot3@example.com', 'manager');

        $this->assertDatabaseHas('request_step_assignments', [
            'request_step_id' => $step->id,
            'user_id' => $managerTwo->id,
        ]);

        $this->assertDatabaseMissing('request_step_assignments', [
            'request_step_id' => $step->id,
            'user_id' => $managerThree->id,
        ]);
    }
}