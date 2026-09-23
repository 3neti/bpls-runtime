<?php

declare(strict_types=1);

namespace App\Assessment;

use App\References\MunicipalFeeCatalog;
use LogicException;

/** Lossless, read-only staging. Never selects an executable fiscal policy. */
final class PricingCatalogDraftPlanner
{
    public function __construct(private readonly MunicipalFeeCatalog $catalog) {}

    /** @return list<PricingDefinitionDraft> */
    public function plan(string $path, string $expectedSha256, int $revision = 1): array
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $expectedSha256)) {
            throw new LogicException('An explicit source SHA-256 is required.');
        }
        $catalog = $this->catalog->read($path);
        if (! hash_equals($expectedSha256, $catalog['source_sha256'])) {
            throw new LogicException('Catalogue differs from the reviewed source fingerprint.');
        }
        if ($catalog['catalog']['code'] !== 'ipil-municipal-fees-v1') {
            throw new LogicException('This draft mapping supports only the reviewed Ipil catalogue.');
        }
        $fees = $catalog['fees'];
        if (! is_array($fees) || ! array_is_list($fees) || $fees === []) {
            throw new LogicException('Catalogue fees must be a nonempty list.');
        }
        $drafts = [];
        $seen = [];
        foreach ($fees as $fee) {
            if (! is_array($fee) || ! is_string($fee['code'] ?? null)
                || ! is_string($fee['basis'] ?? null)
                || (isset($fee['revenue_code']) && ! is_string($fee['revenue_code']))) {
                throw new LogicException('Invalid source fee identity, basis or account.');
            }
            $code = $fee['code'];
            if (isset($seen[$code])) {
                throw new LogicException('Duplicate source fee identity: '.$code);
            }
            $seen[$code] = true;
            $tax = $catalog['tax_definition_evidence_by_code'][$code] ?? null;
            $issues = ['policy_not_adopted'];
            if (! isset($fee['revenue_code'])) {
                $issues[] = 'revenue_account_unmapped';
            }
            if (in_array($fee['basis'], ['legacy_unresolved', 'office_determination'], true)
                || ($fee['basis'] === 'none' && ($fee['calculation_type'] ?? null) === 'formula')) {
                $issues[] = 'basis_requires_reconciliation';
            }
            if ($tax !== null) {
                $issues[] = 'tax_definition_preserved_not_executable';
            }
            $drafts[] = new PricingDefinitionDraft(
                code: $code,
                revision: $revision,
                method: 'source_observed',
                basis: $fee['basis'],
                unitCode: null,
                amountMinor: null,
                currency: 'PHP',
                revenueAccountCode: $fee['revenue_code'] ?? null,
                sourceSha256: $catalog['source_sha256'],
                sourceLocator: 'fees/'.$code,
                sourceEvidence: [
                    'schema_version' => 1,
                    'catalog' => $catalog['catalog'],
                    'fee' => $fee,
                    'tax_definition' => $tax,
                    'issues' => $issues,
                ],
            );
        }

        return $drafts;
    }
}
