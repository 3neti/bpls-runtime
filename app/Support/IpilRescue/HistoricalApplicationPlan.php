<?php

namespace App\Support\IpilRescue;

use InvalidArgumentException;

final readonly class HistoricalApplicationPlan
{
    public function __construct(
        public string $sourceIdentitySha256,
        public string $sourceApplicationType,
        public string $sourceStatus,
        public ?int $sourceYear,
    ) {
        if (preg_match('/^[a-f0-9]{64}$/', $sourceIdentitySha256) !== 1) {
            throw new InvalidArgumentException('Historical Application planning requires a hashed SourceIdentity.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source_identity_sha256' => $this->sourceIdentitySha256,
            'source_application_type' => $this->sourceApplicationType,
            'source_status' => $this->sourceStatus,
            'source_year' => $this->sourceYear,
            'target_status' => 'historical_evidence',
            'operationally_eligible' => false,
            'actor_id' => null,
            'current_liability' => null,
            'allowed_actions' => [],
        ];
    }
}
