<?php

declare(strict_types=1);

namespace App\Assessment;

use App\Exceptions\UnsupportedAssessmentPolicy;
use InvalidArgumentException;

/** Diagnostic only. No successful/zero-price state exists for draft evidence. */
final readonly class PricingDraftResolution
{
    public function __construct(public string $status, public int $candidateCount)
    {
        if (! in_array($status, ['no_match', 'ambiguous_match', 'conflicting_source', 'policy_disabled'], true)
            || $candidateCount < 0
            || ($status === 'no_match' && $candidateCount !== 0)
            || ($status !== 'no_match' && $candidateCount === 0)
            || ($status === 'ambiguous_match' && $candidateCount < 2)
            || ($status === 'policy_disabled' && $candidateCount !== 1)) {
            throw new InvalidArgumentException('Invalid draft resolution result.');
        }
    }

    /** Stops before an AssessmentPriceComponentInput or Price can be constructed. */
    public function requirePriceComponent(): never
    {
        throw new UnsupportedAssessmentPolicy('Pricing draft resolution blocked: '.$this->status.'. Draft evidence grants no fiscal authority.');
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return [
            'schema_version' => 1,
            'status' => $this->status,
            'candidate_count' => $this->candidateCount,
            'amount_minor' => null,
            'executable' => false,
        ];
    }
}
