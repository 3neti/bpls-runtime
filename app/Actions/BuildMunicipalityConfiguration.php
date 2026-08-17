<?php

namespace App\Actions;

class BuildMunicipalityConfiguration
{
    public function __construct(
        private readonly DescribeMunicipalityOfficialConfiguration $describeOfficials,
    ) {}

    /** @return array<string, mixed> */
    public function handle(): array
    {
        $officialConfiguration = $this->describeOfficials->handle();

        return [
            'identity' => [
                'municipality_name' => (string) config('municipality.name', 'Municipality of Ipil'),
                'province' => (string) config('municipality.province', 'Zamboanga Sibugay'),
                'system_name' => (string) config('municipality.system_name', 'Business Permit and Licensing System'),
            ],
            'officials' => $officialConfiguration['officials'],
            'document_associations' => $officialConfiguration['document_associations'],
            'authority_chain' => $officialConfiguration['authority_chain'],
            'authority' => [
                ...$officialConfiguration['summary'],
                'permit_issuance_authorized' => false,
                'permit_release_authorized' => false,
                'legal_effect_authorized' => false,
                'policy_note' => $officialConfiguration['policy_note'],
            ],
            'source' => [
                'type' => 'runtime_configuration',
                'legacy_source_status' => 'characterized',
                'production_snapshot_status' => 'shape_observed_values_not_imported',
                'production_settings_record_count' => 1,
                'effective_dates_evidenced' => false,
                'persisted_administration' => false,
                'read_only' => true,
                'policy_note' => 'Values are supplied through Laravel runtime configuration. Production names and titles were observed but are not imported automatically; municipal acceptance and a governed change process remain separate requirements.',
            ],
        ];
    }
}
