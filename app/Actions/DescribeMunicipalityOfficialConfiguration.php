<?php

namespace App\Actions;

use Illuminate\Support\Arr;

final class DescribeMunicipalityOfficialConfiguration
{
    /** @return array<string, mixed> */
    public function handle(): array
    {
        $configuredOfficials = config('municipality.officials', []);
        $configuredOfficials = is_array($configuredOfficials) ? $configuredOfficials : [];

        $officials = collect($configuredOfficials)
            ->filter(fn (mixed $official): bool => is_array($official))
            ->map(function (array $official, string $key): array {
                $name = trim((string) Arr::get($official, 'name'));
                $effectiveFrom = $this->nullableString(Arr::get($official, 'effective_from'));
                $effectiveUntil = $this->nullableString(Arr::get($official, 'effective_until'));
                $provenance = Arr::get($official, 'provenance', []);
                $provenance = is_array($provenance) ? $provenance : [];

                return [
                    'key' => $key,
                    'role' => (string) Arr::get($official, 'role', 'Unspecified official'),
                    'name' => $name === '' ? 'Unverified official' : $name,
                    'title' => (string) Arr::get($official, 'title', 'Unspecified title'),
                    'configuration_status' => str_starts_with(strtolower($name), 'unverified') || $name === ''
                        ? 'placeholder'
                        : 'configured',
                    'configured_authority_claim' => str((string) Arr::get($official, 'configured_authority_claim', 'unverified'))
                        ->lower()
                        ->toString(),
                    'authorized_signatory' => false,
                    'effective_term' => [
                        'effective_from' => $effectiveFrom,
                        'effective_until' => $effectiveUntil,
                        'status' => $effectiveFrom !== null || $effectiveUntil !== null
                            ? 'configured_not_authorized'
                            : 'not_evidenced',
                    ],
                    'provenance' => [
                        'source_type' => 'runtime_configuration',
                        'legacy_fields' => array_values(array_filter(
                            Arr::get($provenance, 'legacy_fields', []),
                            is_string(...),
                        )),
                        'legacy_source_status' => (string) Arr::get($provenance, 'legacy_source_status', 'unknown'),
                        'production_snapshot_status' => (string) Arr::get($provenance, 'production_snapshot_status', 'unknown'),
                    ],
                ];
            })
            ->values();

        $officialsByKey = $officials->keyBy('key');
        $configuredAssociations = config('municipality.document_associations', []);
        $configuredAssociations = is_array($configuredAssociations) ? $configuredAssociations : [];
        $documentAssociations = collect($configuredAssociations)
            ->filter(fn (mixed $association): bool => is_array($association))
            ->map(function (array $association) use ($officialsByKey): array {
                $officialKey = (string) Arr::get($association, 'official_key');
                $official = $officialsByKey->get($officialKey);

                return [
                    'official_key' => $officialKey,
                    'official_role' => is_array($official) ? $official['role'] : 'Unknown official',
                    'document_type' => (string) Arr::get($association, 'document_type', 'unspecified_document'),
                    'relationship' => (string) Arr::get($association, 'relationship', 'unspecified_relationship'),
                    'current_runtime_use' => (bool) Arr::get($association, 'current_runtime_use', false),
                    'legacy_renderer_status' => (string) Arr::get($association, 'legacy_renderer_status', 'unknown'),
                    'production_layout_status' => (string) Arr::get($association, 'production_layout_status', 'unknown'),
                    'authorizes_signature' => false,
                    'authorizes_issuance' => false,
                    'authorizes_legal_effect' => false,
                ];
            })
            ->values();

        return [
            'officials' => $officials->all(),
            'document_associations' => $documentAssociations->all(),
            'summary' => [
                'official_count' => $officials->count(),
                'configured_official_count' => $officials->where('configuration_status', 'configured')->count(),
                'document_association_count' => $documentAssociations->count(),
                'current_document_association_count' => $documentAssociations->where('current_runtime_use', true)->count(),
                'effective_term_evidence_count' => $officials->filter(
                    fn (array $official): bool => $official['effective_term']['status'] !== 'not_evidenced',
                )->count(),
                'authorized_signatory_count' => 0,
            ],
            'authority_chain' => [
                ['key' => 'configured_official', 'status' => 'evidence_visible', 'satisfied' => $officials->isNotEmpty()],
                ['key' => 'document_signatory', 'status' => 'configuration_evidence_only', 'satisfied' => $documentAssociations->where('current_runtime_use', true)->isNotEmpty()],
                ['key' => 'authorized_signatory', 'status' => 'unresolved', 'satisfied' => false],
                ['key' => 'issuance_authority', 'status' => 'unresolved', 'satisfied' => false],
                ['key' => 'legal_effect', 'status' => 'unresolved', 'satisfied' => false],
            ],
            'policy_note' => 'Configured officials and document associations are historical or presentation evidence only. They do not establish an authorized signatory, permit issuance authority, release, or legal effect.',
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
