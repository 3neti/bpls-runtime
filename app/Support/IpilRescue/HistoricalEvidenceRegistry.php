<?php

namespace App\Support\IpilRescue;

use RuntimeException;

final class HistoricalEvidenceRegistry
{
    /** @var array<string, string> */
    private array $fingerprints = [];

    /**
     * Records a synthetic materialization intent.
     *
     * @return bool True for a new intent; false for an identical replay.
     */
    public function record(HistoricalEvidencePlan $plan): bool
    {
        $key = $plan->sourceIdentitySha256;
        $fingerprint = hash('sha256', CanonicalJson::encode($plan->toArray()));

        if (! isset($this->fingerprints[$key])) {
            $this->fingerprints[$key] = $fingerprint;

            return true;
        }

        if (! hash_equals($this->fingerprints[$key], $fingerprint)) {
            throw new RuntimeException('A SourceIdentity cannot materialize conflicting historical targets.');
        }

        return false;
    }
}
