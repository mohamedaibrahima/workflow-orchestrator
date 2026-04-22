<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestStep extends Model
{
    protected $table = 'request_step_instances';

    protected $fillable = [
        'request_id',
        'workflow_step_id',
        'name',
        'role_id',
        'sequence_order',
        'execution_type',
        'approval_mode',
        'parallel_group',
        'status',
        'acted_at',
        'acted_by',
        'comment',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class, 'role_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(StepAction::class, 'request_step_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RequestStepAssignment::class, 'request_step_id');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class, 'request_step_id');
    }
}