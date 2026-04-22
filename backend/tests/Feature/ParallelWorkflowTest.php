<?php

namespace Tests\Feature;

use App\Jobs\OrchestrateWorkflow;
use App\Jobs\ProcessStepApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Support\WorkflowTestHelpers;
use Tests\TestCase;

class ParallelWorkflowTest extends TestCase
{
    use RefreshDatabase, WorkflowTestHelpers;

    public function test_parallel_group_requires_all_parallel_steps_before_advancing(): void
    {
        $employee = $this->createUserWithRole('Employee Parallel', 'employee.parallel@example.com', 'employee');
        $finance = $this->createUserWithRole('Finance Parallel', 'finance.parallel@example.com', 'finance');
        $procurement = $this->createUserWithRole('Procurement Parallel', 'procurement.parallel@example.com', 'procurement');
        $director = $this->createUserWithRole('Director Parallel', 'director.parallel@example.com', 'director');

        $workflowType = $this->createWorkflowTypeWithSteps('Parallel Purchase', [
            [
                'name' => 'Finance Review',
                'role_id' => $finance->roles->first()->id,
                'sequence_order' => 1,
                'execution_type' => 'parallel',
                'approval_mode' => 'any',
                'parallel_group' => 'grp-a',
            ],
            [
                'name' => 'Procurement Review',
                'role_id' => $procurement->roles->first()->id,
                'sequence_order' => 1,
                'execution_type' => 'parallel',
                'approval_mode' => 'any',
                'parallel_group' => 'grp-a',
            ],
            [
                'name' => 'Director Approval',
                'role_id' => $director->roles->first()->id,
                'sequence_order' => 2,
                'execution_type' => 'sequential',
                'approval_mode' => 'any',
            ],
        ]);

        $this->actingAs($employee, 'api');
        $response = $this->postJson('/api/requests', [
            'workflow_type_id' => $workflowType->id,
            'payload' => ['amount' => 7000],
        ])->assertCreated();

        $requestId = $response->json('data.id');
        OrchestrateWorkflow::dispatchSync($requestId);

        $request = \App\Models\Request::with('steps')->findOrFail($requestId);
        $financeStep = $request->steps->firstWhere('name', 'Finance Review');
        $procurementStep = $request->steps->firstWhere('name', 'Procurement Review');
        $directorStep = $request->steps->firstWhere('name', 'Director Approval');

        ProcessStepApproval::dispatchSync($request->id, $financeStep->id, 'approve', 'finance ok', $finance->id, 'parallel-finance-1');
        OrchestrateWorkflow::dispatchSync($request->id);

        $request->refresh();
        $this->assertEquals('in_progress', $request->status);
        $this->assertNotEquals($directorStep->id, $request->current_step_id);

        ProcessStepApproval::dispatchSync($request->id, $procurementStep->id, 'approve', 'procurement ok', $procurement->id, 'parallel-proc-1');
        OrchestrateWorkflow::dispatchSync($request->id);

        $request->refresh();
        $this->assertEquals('in_progress', $request->status);
        $this->assertEquals($directorStep->fresh()->id, $request->current_step_id);
    }
}