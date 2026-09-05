<?php

namespace App\Actions;

use App\Models\PermitApplication;
use App\StakeholderPreview\StakeholderPreviewSafety;

class DescribeProvisionalUatPermitCompletion
{
    public function __construct(private readonly StakeholderPreviewSafety $safety) {}

    /** @return array<string, mixed>|null */
    public function handle(PermitApplication $permitApplication): ?array
    {
        if (! $this->safety->isEnabled()) {
            return null;
        }

        $completion = $permitApplication->provisionalUatPermitCompletion()->with(['decidedBy', 'issuedBy', 'releasedBy'])->first();

        if ($completion === null) {
            if (data_get($permitApplication->metadata, 'lifecycle_cleanroom.semantic_classification') !== 'synthetic_only'
                && data_get($permitApplication->metadata, 'stakeholder_preview') === null) {
                return null;
            }

            return [
                'semantic_classification' => 'provisional_uat',
                'status' => 'not_started',
                'permit_number' => null,
                'signature_applied' => false,
                'released_in_preview' => false,
                'production_authority' => false,
            ];
        }

        return [
            'semantic_classification' => $completion->semantic_classification,
            'status' => $completion->status,
            'decision' => $completion->decision,
            'reason' => $completion->reason,
            'permit_number' => $completion->permit_number,
            'issued_in_preview' => $completion->issued_at !== null,
            'issued_by' => $completion->issuedBy?->name,
            'issued_at' => $completion->issued_at?->toIso8601String(),
            'valid_until' => $completion->valid_until?->toDateString(),
            'signature_applied' => $completion->synthetic_signature_reference !== null,
            'synthetic_signature_reference' => $completion->synthetic_signature_reference,
            'decided_by' => $completion->decidedBy?->name,
            'decided_at' => $completion->decided_at?->toIso8601String(),
            'released_in_preview' => $completion->released_at !== null,
            'released_by' => $completion->releasedBy?->name,
            'released_at' => $completion->released_at?->toIso8601String(),
            'production_authority' => false,
        ];
    }
}
