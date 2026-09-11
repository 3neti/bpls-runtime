<?php

namespace App\Support\IpilRescue;

final readonly class IpilSeedPlan
{
    /** @param array<string, mixed> $semanticPayload */
    public function __construct(public array $semanticPayload, public string $fingerprint, public string $generatedAt) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->semanticPayload + [
            'plan_id' => 'ipil-seed-plan-'.substr($this->fingerprint, 0, 16),
            'semantic_fingerprint_sha256' => $this->fingerprint,
            'generated_at' => $this->generatedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function executionManifest(): array
    {
        $payload = [
            'schema_version' => 'bpls.ipil-seed-execution-manifest.v1',
            'plan_id' => $this->toArray()['plan_id'],
            'plan_fingerprint_sha256' => $this->fingerprint,
            'corpus' => $this->semanticPayload['corpus'],
            'mapping_profile' => $this->semanticPayload['mapping_profile'],
            'planner' => $this->semanticPayload['planner'],
            'target_environment' => 'local-postgresql',
            'execution_authorized' => false,
            'domain_writes' => false,
            'network_access' => false,
            'phases' => $this->semanticPayload['phases'],
            'coverage' => $this->semanticPayload['coverage'],
            'parity_anchors' => $this->semanticPayload['accepted_evidence'],
            'stop_conditions' => $this->semanticPayload['stop_conditions'],
        ];

        return $payload + ['semantic_fingerprint_sha256' => hash('sha256', CanonicalJson::encode($payload))];
    }
}
