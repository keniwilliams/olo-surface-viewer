<?php

namespace App\Services\OrganState;

class OrganStateSummary
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $readStatus,
        public readonly ?string $lastSuccessfulReadAt,
        public readonly ?string $lastObservedActivityAt,
        public readonly string $stalenessState,
        public readonly string $latestMessage,
        public readonly ?string $latestError,
        public readonly string $source,
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'read_status' => $this->readStatus,
            'last_successful_read_at' => $this->lastSuccessfulReadAt,
            'last_observed_activity_at' => $this->lastObservedActivityAt,
            'staleness_state' => $this->stalenessState,
            'latest_message' => $this->latestMessage,
            'latest_error' => $this->latestError,
            'source' => $this->source,
        ];
    }
}
