<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalEvent;
use App\Models\RepairJob;

final class ApprovalEventService
{
    public function record(
        RepairJob $job,
        string $eventType,
        string $actorType,
        string $actorId = 'unknown',
        array $payload = []
    ): ApprovalEvent {
        return ApprovalEvent::create([
            'garage_id' => $job->garage_id,
            'job_id' => $job->id,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'event_type' => $eventType,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }

    public function recordBySystem(RepairJob $job, string $eventType, array $payload = []): ApprovalEvent
    {
        return $this->record($job, $eventType, ApprovalEvent::ACTOR_SYSTEM, 'system', $payload);
    }
}
