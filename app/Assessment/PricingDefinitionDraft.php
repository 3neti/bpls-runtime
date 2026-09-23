<?php

declare(strict_types=1);

namespace App\Assessment;

use InvalidArgumentException;
use LogicException;

/** Non-executable proposal, never a resolved assessment component. */
final readonly class PricingDefinitionDraft
{
    /** @param array<string, mixed> $sourceEvidence */
    public function __construct(
        public string $code,
        public int $revision,
        public string $method,
        public string $basis,
        public ?string $unitCode,
        public ?int $amountMinor,
        public string $currency,
        public ?string $revenueAccountCode,
        public string $sourceSha256,
        public string $sourceLocator,
        public array $sourceEvidence,
    ) {
        if (trim($code) === '' || strlen($code) > 100 || $revision < 1
            || ! in_array($method, ['fixed', 'quantity_rate', 'manual_determination', 'source_observed'], true)
            || trim($basis) === '' || strlen($basis) > 100
            || ! preg_match('/^[A-Z]{3}$/D', $currency)
            || ! preg_match('/^[a-f0-9]{64}$/D', $sourceSha256)
            || trim($sourceLocator) === '' || strlen($sourceLocator) > 1000
            || ($amountMinor !== null && $amountMinor < 0)
            || ($unitCode !== null && (trim($unitCode) === '' || strlen($unitCode) > 50))
            || ($revenueAccountCode !== null && (trim($revenueAccountCode) === '' || strlen($revenueAccountCode) > 100))) {
            throw new InvalidArgumentException('Invalid pricing draft identity, units, money or provenance.');
        }
        if (($method === 'fixed' && $amountMinor === null)
            || ($method === 'quantity_rate' && ($amountMinor === null || $unitCode === null || $basis === 'none'))
            || (in_array($method, ['manual_determination', 'source_observed'], true) && $amountMinor !== null)) {
            throw new InvalidArgumentException('Unresolved amounts must remain null; priced methods require explicit basis and amount.');
        }
        self::assertEvidence($sourceEvidence);
    }

    public function assertExecutable(): never
    {
        throw new LogicException('Pricing definition drafts cannot authorize an Assessment.');
    }

    /** @return array<string, mixed> */
    public function toRecord(): array
    {
        return [
            'code' => $this->code, 'revision' => $this->revision, 'method' => $this->method,
            'basis' => $this->basis, 'unit_code' => $this->unitCode, 'amount_minor' => $this->amountMinor,
            'currency' => $this->currency, 'revenue_account_code' => $this->revenueAccountCode,
            'source_sha256' => $this->sourceSha256, 'source_locator' => $this->sourceLocator,
            'source_evidence' => $this->sourceEvidence,
        ];
    }

    private static function assertEvidence(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $child) {
                self::assertEvidence($child);
            }

            return;
        }
        if ($value !== null && ! is_string($value) && ! is_int($value) && ! is_bool($value)) {
            throw new InvalidArgumentException('Evidence decimals must be exact strings; executable objects are forbidden.');
        }
    }
}
