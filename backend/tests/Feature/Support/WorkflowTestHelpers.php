<?php

namespace Tests\Feature\Support;

use App\Models\Request;
use App\Models\User;
use App\Models\WorkflowType;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

trait WorkflowTestHelpers
{
    protected function createUserWithRole(string $name, string $email, string $roleName): User
    {
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'api',
        ]);

        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
        ]);

        $user->assignRole($role);

        return $user;
    }

    protected function createWorkflowTypeWithSteps(string $name, array $steps): WorkflowType
    {
        $workflowType = WorkflowType::query()->create([
            'name' => $name,
            'code' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
            'description' => $name . ' workflow',
            'is_active' => true,
        ]);

        foreach ($steps as $index => $step) {
            $stepName = $step['name'];
            $stepKey = $step['step_key'] ?? Str::slug($stepName) ?: ('step-' . ($index + 1));

            $workflowType->steps()->create([
                'name' => $stepName,
                'step_key' => $stepKey,
                'role_id' => $step['role_id'],
                'sequence_order' => $step['sequence_order'],
                'execution_type' => $step['execution_type'] ?? 'sequential',
                'approval_mode' => $step['approval_mode'] ?? 'any',
                'parallel_group' => $step['parallel_group'] ?? null,
                'is_active' => true,
            ]);
        }

        return $workflowType->fresh(['steps']);
    }

    protected function createRequestForWorkflow(User $employee, WorkflowType $workflowType, array $payload = []): Request
    {
        return Request::query()->create([
            'requester_id' => $employee->id,
            'workflow_type_id' => $workflowType->id,
            'current_step_id' => null,
            'status' => 'pending',
            'payload' => $payload ?: ['amount' => 5000, 'currency' => 'EGP'],
            'approved_steps' => [],
        ]);
    }
}