<?php

namespace Tests\Feature;

use App\Jobs\OrchestrateWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Support\WorkflowTestHelpers;
use Tests\TestCase;

class SequentialWorkflowTest extends TestCase
{
    use RefreshDatabase, WorkflowTestHelpers;

    public function test_sequential_request_creation_snapshots_steps_and_assignments(): void
    {
        $employee = $this->createUserWithRole('Employee One', 'employee1@example.com', 'employee');
        $manager = $this->createUserWithRole('Manager One', 'manager1@example.com', 'manager');
        $finance = $this->createUserWithRole('Finance One', 'finance1@example.com', 'finance');

        $workflowType = $this->createWorkflowTypeWithSteps('Purchase Request', [
            [
                'name' => 'Manager Approval',
                'role_id' => $manager->roles->first()->id,
                'sequence_order' => 1,
                'approval_mode' => 'any',
            ],
            [
                'name' => 'Finance Approval',
                'role_id' => $finance->roles->first()->id,
                'sequence_order' => 2,
                'approval_mode' => 'any',
            ],
        ]);

        $this->actingAs($employee, 'api');

        $response = $this->postJson('/api/requests', [
            'workflow_type_id' => $workflowType->id,
            'payload' => ['amount' => 1000, 'vendor' => 'ABC'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.workflow_type_id', $workflowType->id)
            ->assertJsonCount(2, 'data.steps');

        $this->assertDatabaseCount('request_step_instances', 2);
        $this->assertDatabaseCount('request_step_assignments', 2);
    }

    public function test_sequential_workflow_happy_path_can_complete(): void
    {
        $employee = $this->createUserWithRole('Employee Two', 'employee2@example.com', 'employee');
        $manager = $this->createUserWithRole('Manager Two', 'manager2@example.com', 'manager');
        $finance = $this->createUserWithRole('Finance Two', 'finance2@example.com', 'finance');

        $workflowType = $this->createWorkflowTypeWithSteps('Expense Request', [
            [
                'name' => 'Manager Approval',
                'role_id' => $manager->roles->first()->id,
                'sequence_order' => 1,
                'approval_mode' => 'any',
            ],
            [
                'name' => 'Finance Approval',
                'role_id' => $finance->roles->first()->id,
                'sequence_order' => 2,
                'approval_mode' => 'any',
            ],
        ]);

        $request = $this->createRequestForWorkflow($employee, $workflowType, ['amount' => 2000]);

        foreach ($workflowType->steps as $workflowStep) {
            $request->steps()->create([
                'workflow_step_id' => $workflowStep->id,
                'name' => $workflowStep->name,
                'role_id' => $workflowStep->role_id,
                'sequence_order' => $workflowStep->sequence_order,
                'execution_type' => $workflowStep->execution_type,
                'approval_mode' => $workflowStep->approval_mode,
                'parallel_group' => $workflowStep->parallel_group,
                'status' => 'pending',
            ]);
        }

        foreach ($request->steps as $step) {
            $approver = $step->role_id === $manager->roles->first()->id ? $manager : $finance;

            $request->assignments()->create([
                'request_step_id' => $step->id,
                'role_id' => $step->role_id,
                'user_id' => $approver->id,
                'status' => 'pending',
            ]);
        }

        OrchestrateWorkflow::dispatchSync($request->id);

        $managerStep = $request->fresh()->steps()->where('sequence_order', 1)->first();
        $financeStep = $request->fresh()->steps()->where('sequence_order', 2)->first();

        \App\Jobs\ProcessStepApproval::dispatchSync(
            $request->id,
            $managerStep->id,
            'approve',
            'manager approved',
            $manager->id,
            'seq-manager-approve-1'
        );

        OrchestrateWorkflow::dispatchSync($request->id);

        \App\Jobs\ProcessStepApproval::dispatchSync(
            $request->id,
            $financeStep->id,
            'approve',
            'finance approved',
            $finance->id,
            'seq-finance-approve-1'
        );

        OrchestrateWorkflow::dispatchSync($request->id);

        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'status' => 'completed',
        ]);
    }
}