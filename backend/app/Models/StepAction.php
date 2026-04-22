<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StepAction extends Model
{
    protected $fillable = [
        'request_id',
        'request_step_id',
        'user_id',
        'action',
        'comment',
        'idempotency_key',
        'is_effective',
        'metadata',
        'acted_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_effective' => 'boolean',
        'acted_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function requestStep(): BelongsTo
    {
        return $this->belongsTo(RequestStep::class, 'request_step_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}