<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStep extends Model
{
    protected $fillable = [
        'workflow_type_id',
        'step_key',
        'name',
        'role_id',
        'sequence_order',
        'execution_type',
        'parallel_group',
        'approval_mode',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function workflowType(): BelongsTo
    {
        return $this->belongsTo(WorkflowType::class, 'workflow_type_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class, 'role_id');
    }
}