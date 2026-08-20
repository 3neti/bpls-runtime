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

        $completion = $permitApplication->provisionalUatPermitCompletion()->with(['decidedBy', 'releasedBy'])->first();

        if ($completion === null) {
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
