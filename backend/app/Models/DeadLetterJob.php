<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeadLetterJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'queue',
        'job_class',
        'related_request_id',
        'related_step_instance_id',
        'payload',
        'exception_message',
        'exception_trace',
        'attempts',
        'status',
        'failed_at',
        'retried_at',
        'resolved_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'failed_at' => 'datetime',
        'retried_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'related_request_id');
    }

    public function stepInstance()
    {
        return $this->belongsTo(RequestStepInstance::class, 'related_step_instance_id');
    }
}