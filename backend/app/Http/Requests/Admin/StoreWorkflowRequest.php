<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:workflow_types,name'],
            'code' => ['nullable', 'string', 'max:100', 'unique:workflow_types,code'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],

            'steps' => ['required', 'array', 'min:1'],
            'steps.*.step_key' => ['required', 'string', 'max:255'],
            'steps.*.name' => ['required', 'string', 'max:255'],
            'steps.*.role_id' => ['required', 'integer', 'exists:roles,id'],
            'steps.*.sequence_order' => ['required', 'integer', 'min:1'],
            'steps.*.execution_type' => ['required', Rule::in(['sequential', 'parallel'])],
            'steps.*.parallel_group' => ['nullable', 'string', 'max:255'],
            'steps.*.approval_mode' => ['required', Rule::in(['any', 'all'])],
            'steps.*.is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $steps = $this->input('steps', []);
            $stepKeys = [];

            foreach ($steps as $index => $step) {
                $stepKey = $step['step_key'] ?? null;
                $executionType = $step['execution_type'] ?? null;
                $parallelGroup = $step['parallel_group'] ?? null;

                if ($stepKey && in_array($stepKey, $stepKeys, true)) {
                    $validator->errors()->add(
                        "steps.$index.step_key",
                        'step_key must be unique within the workflow type.'
                    );
                }

                if ($stepKey) {
                    $stepKeys[] = $stepKey;
                }

                if ($executionType === 'parallel' && blank($parallelGroup)) {
                    $validator->errors()->add(
                        "steps.$index.parallel_group",
                        'parallel_group is required when execution_type is parallel.'
                    );
                }

                if ($executionType === 'sequential' && filled($parallelGroup)) {
                    $validator->errors()->add(
                        "steps.$index.parallel_group",
                        'parallel_group must be null when execution_type is sequential.'
                    );
                }
            }
        });
    }
}