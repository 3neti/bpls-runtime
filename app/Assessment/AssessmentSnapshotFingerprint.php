<?php

namespace App\Assessment;

use App\Models\Assessment;
use App\Models\AssessmentLine;
use Illuminate\Support\Arr;

class AssessmentSnapshotFingerprint
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(Assessment $assessment): array
    {
        $assessment->loadMissing(['lines' => fn ($query) => $query->orderBy('id')]);

        return $this->normalize([
            'assessment_id' => $assessment->id,
            'permit_application_id' => $assessment->permit_application_id,
            'sequence' => $assessment->sequence,
            'status' => $assessment->status->value,
            'assessed_by_id' => $assessment->assessed_by_id,
            'assessed_at' => $assessment->assessed_at?->toIso8601String(),
            'superseded_at' => $assessment->superseded_at?->toIso8601String(),
            'total_amount_cents' => $assessment->total_amount_cents,
            'source_snapshot' => $assessment->source_snapshot,
            'lines' => $assessment->lines
                ->sortBy('id')
                ->values()
                ->map(fn (AssessmentLine $line): array => [
                    'id' => $line->id,
                    'permit_application_line_id' => $line->permit_application_line_id,
                    'fee_rule_id' => $line->fee_rule_id,
                    'business_permit_evaluation_item_id' => $line->business_permit_evaluation_item_id,
                    'line_of_business_id' => $line->line_of_business_id,
                    'code' => $line->code,
                    'name' => $line->name,
                    'category' => $line->category->value,
                    'calculation_type' => $line->calculation_type->value,
                    'basis' => $line->basis,
                    'basis_amount_cents' => $line->basis_amount_cents,
                    'amount_cents' => $line->amount_cents,
                    'legal_basis' => $line->legal_basis,
                    'rule_snapshot' => $line->rule_snapshot,
                ])
                ->all(),
        ]);
    }

    public function hash(Assessment $assessment): string
    {
        return hash('sha256', json_encode(
            $this->snapshot($assessment),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function normalize(array $value): array
    {
        if (Arr::isAssoc($value)) {
            ksort($value);
        }

        return array_map(
            fn (mixed $item): mixed => is_array($item) ? $this->normalize($item) : $item,
            $value,
        );
    }
}
