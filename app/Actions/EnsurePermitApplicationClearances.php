<?php

namespace App\Actions;

use App\Enums\PermitClearanceStatus;
use App\Models\PermitApplication;
use LogicException;

class EnsurePermitApplicationClearances
{
    /**
     * @return list<array{code: string, label: string, policy_note: string}>
     */
    public function defaultChecklist(): array
    {
        return [
            [
                'code' => 'bplo_review',
                'label' => 'BPLO review',
                'policy_note' => 'Represents BPLO staff review evidence only; final release authority remains unresolved.',
            ],
            [
                'code' => 'treasury_payment',
                'label' => 'Treasury payment evidence',
                'policy_note' => 'Represents visible payment and receipt evidence; reconciliation policy remains unresolved.',
            ],
            [
                'code' => 'release_authority',
                'label' => 'Release authority',
                'policy_note' => 'Represents the unresolved release/signatory authority boundary, not actual permit issuance.',
            ],
        ];
    }

    public function handle(PermitApplication $permitApplication): PermitApplication
    {
        if ($permitApplication->isHistoricalEvidenceOnly()) {
            throw new LogicException("Historical evidence application [{$permitApplication->id}] cannot receive operational clearance records.");
        }

        foreach ($this->defaultChecklist() as $item) {
            $permitApplication->clearances()->firstOrCreate(
                ['code' => $item['code']],
                [
                    'label' => $item['label'],
                    'status' => PermitClearanceStatus::Pending,
                    'source_snapshot' => [
                        'source' => 'rescue_default_clearance_checklist',
                        'policy_note' => $item['policy_note'],
                    ],
                ],
            );
        }

        return $permitApplication->load([
            'clearances' => fn ($query) => $query->orderBy('id'),
        ]);
    }
}
