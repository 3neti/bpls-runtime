<?php

namespace App\Assessment;

use App\Models\PricingDefinitionDraft;
use App\Models\PricingDraftMapping;
use InvalidArgumentException;

/** Reads every stored draft revision in an exact source cohort; never selects live prices. */
final class PricingMappingCoverageAudit
{
    /** @return array<string, mixed> */
    public function report(string $sourceSha256): array
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $sourceSha256)) {
            throw new InvalidArgumentException('An explicit source SHA-256 is required.');
        }
        $counts = [
            'unmapped' => 0, 'account_unmapped' => 0, 'identity_drift' => 0,
            'account_inactive' => 0, 'mapping_recorded' => 0,
        ];
        $drafts = PricingDefinitionDraft::query()->where('source_sha256', $sourceSha256)->orderBy('id')->get();
        $mappings = PricingDraftMapping::query()
            ->whereIn('pricing_definition_draft_id', $drafts->modelKeys())
            ->with(['item', 'account'])
            ->orderByDesc('revision')->get()->unique('pricing_definition_draft_id')
            ->keyBy('pricing_definition_draft_id');
        foreach ($drafts as $draft) {
            $mapping = $mappings->get($draft->getKey());
            if ($mapping === null) {
                $counts['unmapped']++;

                continue;
            }
            $snapshot = $mapping->getAttribute('identity_snapshot');
            $item = $mapping->item;
            $account = $mapping->account;
            $expected = [
                'schema_version' => 1,
                'classification' => 'mapping_evidence_only',
                'executable' => false,
                'draft_code' => $draft->code,
                'draft_revision' => $draft->revision,
                'source_sha256' => $draft->source_sha256,
                'source_locator' => $draft->source_locator,
                'source_account_code' => $draft->revenue_account_code,
                'charge_code' => $item?->getAttribute('code'),
                'charge_name' => $item?->getAttribute('name'),
                'account_code' => $account?->code,
                'account_name' => $account?->name,
            ];
            $drift = $item === null || ! is_array($snapshot)
                || ($mapping->getAttribute('revenue_account_id') !== null && $account === null);
            foreach ($expected as $key => $value) {
                if (! is_array($snapshot) || ! array_key_exists($key, $snapshot) || $snapshot[$key] !== $value) {
                    $drift = true;
                }
            }
            $state = match (true) {
                $drift => 'identity_drift',
                $account === null => 'account_unmapped',
                ! $account->is_active => 'account_inactive',
                default => 'mapping_recorded',
            };
            $counts[$state]++;
        }

        return [
            'source_sha256' => $sourceSha256,
            'scope' => 'all_stored_draft_revisions_for_source',
            'draft_revision_count' => $drafts->count(),
            'fee_identity_count' => $drafts->unique('code')->count(),
            'state' => $drafts->isEmpty() ? 'no_stored_drafts' : 'audited',
            'counts' => $counts,
            'database_writes' => false,
            'fiscal_readiness' => false,
            'executable' => false,
        ];
    }
}
