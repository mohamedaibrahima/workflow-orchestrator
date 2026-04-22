<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WorkflowStep;
use App\Models\WorkflowType;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        $adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->first();
        $departmentManagerRole = Role::where('name', 'department_manager')->where('guard_name', 'api')->first();
        $financeRole = Role::where('name', 'finance_approver')->where('guard_name', 'api')->first();
        $procurementRole = Role::where('name', 'procurement_approver')->where('guard_name', 'api')->first();

        $workflowType = WorkflowType::firstOrCreate(
            ['code' => 'purchase_request'],
            [
                'name' => 'Purchase Request',
                'description' => 'Workflow for employee purchase requests',
                'is_active' => true,
                'created_by' => $admin?->id,
            ]
        );

        if ($workflowType->steps()->count() === 0) {
            WorkflowStep::create([
                'workflow_type_id' => $workflowType->id,
                'step_key' => 'manager_review',
                'name' => 'Department Manager Review',
                'role_id' => $departmentManagerRole?->id,
                'sequence_order' => 1,
                'execution_type' => 'sequential',
                'parallel_group' => null,
                'approval_mode' => 'any',
                'is_active' => true,
            ]);

            WorkflowStep::create([
                'workflow_type_id' => $workflowType->id,
                'step_key' => 'finance_review',
                'name' => 'Finance Review',
                'role_id' => $financeRole?->id,
                'sequence_order' => 2,
                'execution_type' => 'parallel',
                'parallel_group' => 'group_1',
                'approval_mode' => 'all',
                'is_active' => true,
            ]);

            WorkflowStep::create([
                'workflow_type_id' => $workflowType->id,
                'step_key' => 'procurement_review',
                'name' => 'Procurement Review',
                'role_id' => $procurementRole?->id,
                'sequence_order' => 2,
                'execution_type' => 'parallel',
                'parallel_group' => 'group_1',
                'approval_mode' => 'any',
                'is_active' => true,
            ]);

            WorkflowStep::create([
                'workflow_type_id' => $workflowType->id,
                'step_key' => 'finalization',
                'name' => 'Finalization Step',
                'role_id' => $adminRole?->id,
                'sequence_order' => 3,
                'execution_type' => 'sequential',
                'parallel_group' => null,
                'approval_mode' => 'any',
                'is_active' => true,
            ]);
        }
    }
}