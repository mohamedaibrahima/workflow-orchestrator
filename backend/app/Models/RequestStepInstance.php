<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class RequestStepInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'workflow_step_id',
        'step_key_snapshot',
        'step_name_snapshot',
        'role_id_snapshot',
        'sequence_order_snapshot',
        'execution_type_snapshot',
        'parallel_group_snapshot',
        'approval_mode_snapshot',
        'status',
        'locked_version',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class);
    }

    public function workflowStep()
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    public function roleSnapshot()
    {
        return $this->belongsTo(Role::class, 'role_id_snapshot');
    }

    public function assignments()
    {
        return $this->hasMany(RequestStepAssignment::class);
    }

    public function actions()
    {
        return $this->hasMany(StepAction::class);
    }

    public function auditEvents()
    {
        return $this->hasMany(AuditEvent::class);
    }
}