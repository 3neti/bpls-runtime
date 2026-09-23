<?php

declare(strict_types=1);

namespace App\Assessment;

use InvalidArgumentException;

/** Exact identity selection only; no applicability, band arithmetic or policy adoption. */
final class PricingDraftResolver
{
    /** @param array<array-key, mixed> $candidates Untrusted input; validated as a list of typed drafts below. */
    public function resolve(array $candidates, string $code, int $revision, string $sourceSha256): PricingDraftResolution
    {
        if (trim($code) === '' || $revision < 1 || ! preg_match('/^[a-f0-9]{64}$/D', $sourceSha256)
            || ! array_is_list($candidates)) {
            throw new InvalidArgumentException('Exact draft code, revision and source fingerprint are required.');
        }
        $matches = [];
        foreach ($candidates as $candidate) {
            if (! $candidate instanceof PricingDefinitionDraft) {
                throw new InvalidArgumentException('Only typed draft definitions can be resolved.');
            }
            if ($candidate->code === $code && $candidate->revision === $revision) {
                $matches[] = $candidate;
            }
        }
        if ($matches === []) {
            return new PricingDraftResolution('no_match', 0);
        }
        foreach ($matches as $match) {
            if (! hash_equals($sourceSha256, $match->sourceSha256)) {
                return new PricingDraftResolution('conflicting_source', count($matches));
            }
        }
        if (count($matches) > 1) {
            return new PricingDraftResolution('ambiguous_match', count($matches));
        }

        return new PricingDraftResolution('policy_disabled', 1);
    }
}
