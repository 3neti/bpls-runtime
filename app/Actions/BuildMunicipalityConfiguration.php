<?php

namespace App\Actions;

use Illuminate\Support\Arr;

class BuildMunicipalityConfiguration
{
    /** @return array<string, mixed> */
    public function handle(): array
    {
        $configuredSignatories = config('municipality.signatories.permit', []);
        $configuredSignatories = is_array($configuredSignatories)
            ? array_values(array_filter($configuredSignatories, is_array(...)))
            : [];
        $signatories = collect($configuredSignatories)
            ->map(fn (array $signatory): array => [
                'role' => (string) Arr::get($signatory, 'role', 'Unspecified signatory'),
                'name' => (string) Arr::get($signatory, 'name', 'Unverified signatory'),
                'title' => (string) Arr::get($signatory, 'title', 'Unspecified title'),
                'authority_status' => str((string) Arr::get($signatory, 'authority_status', 'unverified'))
                    ->lower()
                    ->toString(),
            ])
            ->values();
        $verifiedCount = $signatories
            ->where('authority_status', 'verified')
            ->count();

        return [
            'identity' => [
                'municipality_name' => (string) config('municipality.name', 'Municipality of Ipil'),
                'province' => (string) config('municipality.province', 'Zamboanga Sibugay'),
                'system_name' => (string) config('municipality.system_name', 'Business Permit and Licensing System'),
            ],
            'permit_signatories' => $signatories->all(),
            'authority' => [
                'signatory_count' => $signatories->count(),
                'verified_signatory_count' => $verifiedCount,
                'unverified_signatory_count' => $signatories->count() - $verifiedCount,
                'all_signatories_verified' => $signatories->isNotEmpty() && $verifiedCount === $signatories->count(),
                'permit_issuance_authorized' => false,
                'policy_note' => 'Configuration records document presentation evidence only. It does not authorize permit issuance, release, or legal effect.',
            ],
            'source' => [
                'type' => 'runtime_configuration',
                'persisted_administration' => false,
                'read_only' => true,
                'policy_note' => 'Values are supplied through Laravel runtime configuration. Municipal acceptance and a governed change process remain separate requirements.',
            ],
        ];
    }
}
