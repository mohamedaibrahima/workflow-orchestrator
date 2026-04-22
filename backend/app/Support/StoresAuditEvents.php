<?php

namespace App\Support;

use App\Models\AuditEvent;

trait StoresAuditEvents
{
    protected function storeAuditEvent(
        int $requestId,
        ?int $requestStepId,
        ?int $userId,
        string $eventType,
        ?string $eventKey = null,
        ?array $payload = null,
    ): void {
        if ($eventKey) {
            $existing = AuditEvent::query()
                ->where('event_key', $eventKey)
                ->first();

            if ($existing) {
                return;
            }
        }

        AuditEvent::create([
            'request_id' => $requestId,
            'request_step_id' => $requestStepId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'event_key' => $eventKey,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}