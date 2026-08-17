<?php

namespace App\Actions;

final class DescribePermitDocumentConfiguration
{
    public function __construct(
        private readonly DescribeMunicipalityOfficialConfiguration $describeOfficials,
    ) {}

    /**
     * @return array{
     *     municipality: array{name: string, province: string, system_name: string},
     *     permit_signatories: list<array{role: string, name: string, title: string, authority_status: string}>,
     *     authority_verified: bool,
     *     policy_note: string
     * }
     */
    public function handle(): array
    {
        $officialConfiguration = $this->describeOfficials->handle();
        $officialsByKey = [];

        foreach ($officialConfiguration['officials'] ?? [] as $official) {
            if (! is_array($official) || ! isset($official['key'], $official['role'], $official['name'], $official['title'])) {
                continue;
            }

            $officialsByKey[(string) $official['key']] = [
                'role' => (string) $official['role'],
                'name' => (string) $official['name'],
                'title' => (string) $official['title'],
            ];
        }

        $signatories = [];

        foreach ($officialConfiguration['document_associations'] ?? [] as $association) {
            if (! is_array($association)
                || ($association['document_type'] ?? null) !== 'permit_artifact'
                || ($association['relationship'] ?? null) !== 'configured_signatory'
                || ($association['current_runtime_use'] ?? null) !== true) {
                continue;
            }

            $official = $officialsByKey[(string) ($association['official_key'] ?? '')] ?? null;

            if ($official === null) {
                continue;
            }

            $signatories[] = [
                'role' => $official['role'],
                'name' => $official['name'],
                'title' => $official['title'],
                'authority_status' => 'unresolved',
            ];
        }

        return [
            'municipality' => [
                'name' => (string) config('municipality.name', 'Municipality of Ipil'),
                'province' => (string) config('municipality.province', 'Zamboanga Sibugay'),
                'system_name' => (string) config('municipality.system_name', 'Business Permit and Licensing System'),
            ],
            'permit_signatories' => $signatories,
            'authority_verified' => false,
            'policy_note' => 'Configured signatories are document associations only; they do not establish authorized signatures or authorize permit issuance, release, or legal effect.',
        ];
    }
}
