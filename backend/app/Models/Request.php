<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Request extends Model
{
    protected $fillable = [
        'requester_id',
        'workflow_type_id',
        'current_step_id',
        'rejected_step_id',
        'status',
        'payload',
        'approved_steps',
    ];

    protected $casts = [
        'payload' => 'array',
        'approved_steps' => 'array',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function workflowType(): BelongsTo
    {
        return $this->belongsTo(WorkflowType::class, 'workflow_type_id');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(RequestStep::class, 'current_step_id');
    }

    public function rejectedStep(): BelongsTo
    {
        return $this->belongsTo(RequestStep::class, 'rejected_step_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RequestStep::class, 'request_id')->orderBy('sequence_order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RequestStepAssignment::class, 'request_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(StepAction::class, 'request_id');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class, 'request_id');
    }
}