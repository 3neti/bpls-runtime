<?php

namespace App\Actions;

use Illuminate\Support\Arr;

final class DescribePermitDocumentConfiguration
{
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
        $signatories = collect(config('municipality.signatories.permit', []))
            ->map(fn (array $signatory): array => [
                'role' => (string) Arr::get($signatory, 'role', 'Unspecified signatory'),
                'name' => (string) Arr::get($signatory, 'name', 'Unverified signatory'),
                'title' => (string) Arr::get($signatory, 'title', 'Unspecified title'),
                'authority_status' => str((string) Arr::get($signatory, 'authority_status', 'unverified'))->lower()->toString(),
            ])
            ->values()
            ->all();

        return [
            'municipality' => [
                'name' => (string) config('municipality.name', 'Municipality of Ipil'),
                'province' => (string) config('municipality.province', 'Zamboanga Sibugay'),
                'system_name' => (string) config('municipality.system_name', 'Business Permit and Licensing System'),
            ],
            'permit_signatories' => $signatories,
            'authority_verified' => $signatories !== [] && collect($signatories)->every(
                fn (array $signatory): bool => $signatory['authority_status'] === 'verified',
            ),
            'policy_note' => 'Configured signatories are document evidence only; they do not authorize permit release until issuance authority is resolved.',
        ];
    }
}
