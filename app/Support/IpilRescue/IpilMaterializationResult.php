<?php

namespace App\Support\IpilRescue;

final readonly class IpilMaterializationResult
{
    /**
     * @param  array<string, mixed>  $counts
     * @param  list<array<string, mixed>>  $phases
     */
    public function __construct(
        public string $runId,
        public float $durationSeconds,
        public array $counts,
        public array $phases,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'run_id' => $this->runId,
            'duration_seconds' => $this->durationSeconds,
            'counts' => $this->counts,
            'phases' => $this->phases,
        ];
    }
}
