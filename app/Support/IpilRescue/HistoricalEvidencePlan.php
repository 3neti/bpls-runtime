<?php

namespace App\Support\IpilRescue;

use InvalidArgumentException;

final readonly class HistoricalEvidencePlan
{
    /** @param array<string, HistoricalAmount|string|int|bool|null> $facts */
    public function __construct(
        public string $kind,
        public string $sourceIdentitySha256,
        public string $disposition,
        public string $confidence,
        public array $facts,
        public bool $orphaned = false,
    ) {
        if (! in_array($kind, ['financial_bundle', 'payment', 'receipt_claim', 'permit_claim', 'clearance_claim', 'classification', 'document', 'actor'], true)) {
            throw new InvalidArgumentException('The historical evidence kind is unsupported.');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $sourceIdentitySha256) !== 1) {
            throw new InvalidArgumentException('Historical evidence requires a hashed SourceIdentity.');
        }
        if (! in_array($disposition, ['MAP_AS_HISTORICAL_EVIDENCE', 'PRESERVE_UNINTERPRETED', 'DEFER', 'BLOCKED'], true)) {
            throw new InvalidArgumentException('The historical evidence disposition is unsupported.');
        }
        if (! in_array($confidence, ['ESTABLISHED', 'PROBABLE', 'AMBIGUOUS', 'UNKNOWN'], true)) {
            throw new InvalidArgumentException('The historical evidence confidence is unsupported.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'source_identity_sha256' => $this->sourceIdentitySha256,
            'disposition' => $this->disposition,
            'confidence' => $this->confidence,
            'facts' => array_map(
                fn (HistoricalAmount|string|int|bool|null $value): mixed => $value instanceof HistoricalAmount
                    ? ['source_lexeme' => $value->sourceLexeme, 'decimal' => $value->decimal, 'minor_units' => $value->minorUnits]
                    : $value,
                $this->facts,
            ),
            'orphaned' => $this->orphaned,
            'historical' => true,
            'operationally_eligible' => false,
            'creates_current_user' => false,
            'creates_current_finance' => false,
            'creates_current_permit' => false,
            'creates_spatie_media' => false,
        ];
    }
}
