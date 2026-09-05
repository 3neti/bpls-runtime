<?php

namespace App\Actions;

use App\Models\PermitApplication;

final class DescribePermitVerificationBoundary
{
    /**
     * @return array{
     *     reference: string,
     *     url: string,
     *     view_url: string,
     *     status: string,
     *     can_verify_release: bool,
     *     released: bool,
     *     policy_note: string
     * }
     */
    public function handle(PermitApplication $permitApplication): array
    {
        $permitApplication->loadMissing('provisionalUatPermitCompletion');
        $completion = $permitApplication->provisionalUatPermitCompletion;
        $reference = $this->reference($permitApplication);
        $issued = $completion?->issued_at !== null;
        $released = $completion?->released_at !== null;

        return [
            'reference' => $reference,
            'url' => route('public.permits.verify', [
                'permitApplication' => $permitApplication,
                'verificationCode' => $reference,
            ]),
            'view_url' => route('public.permits.verify.view', [
                'permitApplication' => $permitApplication,
                'verificationCode' => $reference,
            ]),
            'status' => $released ? 'released_synthetic_identity' : ($issued ? 'issued_synthetic_identity' : 'artifact_only'),
            'can_verify_release' => false,
            'released' => $released,
            'policy_note' => $issued
                ? 'This reference resolves to the exact synthetic Permit identity. It does not establish production authority, legal validity, revocation status, or broader legal attestation.'
                : 'This reference identifies a generated preview document only. It does not confirm municipal release or legal validity.',
        ];
    }

    public function matches(PermitApplication $permitApplication, string $verificationCode): bool
    {
        return hash_equals($this->reference($permitApplication), $verificationCode);
    }

    private function reference(PermitApplication $permitApplication): string
    {
        $completion = $permitApplication->provisionalUatPermitCompletion;
        $permitNumber = $completion?->permit_number;
        $source = implode('|', [
            $permitApplication->id,
            $permitApplication->application_number ?? '',
            $permitApplication->application_year,
            $permitApplication->business_id,
            $permitApplication->created_at?->toIso8601String() ?? '',
            $permitNumber === null ? '' : $permitNumber,
            $completion?->issued_at?->toIso8601String() ?? '',
        ]);

        return ($completion?->issued_at === null ? 'PVA-' : 'BPV-').$permitApplication->id.'-'.substr(hash('sha256', $source), 0, 16);
    }
}
