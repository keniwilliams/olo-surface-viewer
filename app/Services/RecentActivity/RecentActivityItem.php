<?php

namespace App\Services\RecentActivity;

class RecentActivityItem
{
    public function __construct(
        public readonly string $sourceOrganKey,
        public readonly string $sourceOrganLabel,
        public readonly string $activityType,
        public readonly ?string $activityTimestamp,
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $error,
        public readonly string $sourceReference,
    ) {}

    public function toArray(): array
    {
        return [
            'source_organ_key' => $this->sourceOrganKey,
            'source_organ_label' => $this->sourceOrganLabel,
            'activity_type' => $this->activityType,
            'activity_timestamp' => $this->activityTimestamp,
            'status' => $this->status,
            'message' => $this->message,
            'error' => $this->error,
            'source_reference' => $this->sourceReference,
        ];
    }
}
