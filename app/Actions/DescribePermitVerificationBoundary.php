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
        $reference = $this->reference($permitApplication);

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
            'status' => 'artifact_only',
            'can_verify_release' => false,
            'released' => false,
            'policy_note' => 'This reference identifies a generated preview document only. It does not confirm municipal release or legal validity.',
        ];
    }

    public function matches(PermitApplication $permitApplication, string $verificationCode): bool
    {
        return hash_equals($this->reference($permitApplication), $verificationCode);
    }

    private function reference(PermitApplication $permitApplication): string
    {
        $source = implode('|', [
            $permitApplication->id,
            $permitApplication->application_number ?? '',
            $permitApplication->application_year,
            $permitApplication->business_id,
            $permitApplication->created_at?->toIso8601String() ?? '',
        ]);

        return 'PVA-'.$permitApplication->id.'-'.substr(hash('sha256', $source), 0, 16);
    }
}
