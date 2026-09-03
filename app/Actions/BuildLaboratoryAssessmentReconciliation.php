<?php

namespace App\Actions;

use App\Assessment\AssessmentSnapshotFingerprint;
use App\Models\Assessment;
use App\Models\AssessmentLine;
use Illuminate\Support\Arr;

class BuildLaboratoryAssessmentReconciliation
{
    public function __construct(private readonly AssessmentSnapshotFingerprint $fingerprint) {}

    /** @return array<string, mixed>|null */
    public function handle(Assessment $assessment): ?array
    {
        if (app()->isProduction()) {
            return null;
        }

        $assessment->loadMissing(['lines', 'permitApplication']);
        $metadata = data_get($assessment->permitApplication->metadata, 'laboratory_assessment_reconciliation');

        if (! is_array($metadata)
            || ($metadata['schema_version'] ?? null) !== 'bpls.laboratory-assessment-reconciliation.v1'
            || ($metadata['semantic_classification'] ?? null) !== 'observational_legacy_financial_evidence'
            || ($metadata['operational_authority'] ?? null) !== false
            || ($metadata['production_liability'] ?? null) !== false) {
            return null;
        }

        $historicalAssessment = $metadata['historical_assessment'] ?? null;
        $sourceEvidenceValid = $this->sourceEvidenceValid($historicalAssessment);

        if (! $sourceEvidenceValid || ! is_array($historicalAssessment)) {
            return [
                'status' => 'source_evidence_invalid',
                'comparable' => false,
                'source_reference' => $metadata['source_reference'] ?? null,
                'statement' => 'The preserved laboratory source evidence failed its integrity check, so no amount comparison was made.',
                'operational_effect' => false,
            ];
        }

        $sourceTotalAmountCents = $historicalAssessment['recorded_total_amount_cents'];
        $computedTotalAmountCents = $assessment->total_amount_cents;
        $deltaAmountCents = $computedTotalAmountCents - $sourceTotalAmountCents;

        return [
            'status' => $deltaAmountCents === 0 ? 'exact_match' : 'difference',
            'comparable' => true,
            'source_reference' => $metadata['source_reference'],
            'source_business_category' => $metadata['source_business_category'],
            'source' => [
                'label' => 'Legacy recorded payment-schedule assessment',
                'status' => $historicalAssessment['source_status'],
                'assessed_at' => $historicalAssessment['source_assessed_at'],
                'total_amount_cents' => $sourceTotalAmountCents,
                'component_total_amount_cents' => $historicalAssessment['component_total_amount_cents'],
                'internally_reconciles' => $historicalAssessment['source_internal_reconciles'],
                'evidence_hash' => $historicalAssessment['source_evidence_hash'],
                'schedules' => $historicalAssessment['schedules'],
            ],
            'computed' => [
                'label' => 'New BPLS immutable Assessment',
                'assessment_id' => $assessment->id,
                'total_amount_cents' => $computedTotalAmountCents,
                'component_total_amount_cents' => (int) $assessment->lines->sum('amount_cents'),
                'internally_reconciles' => (int) $assessment->lines->sum('amount_cents') === $computedTotalAmountCents,
                'snapshot_hash' => $this->fingerprint->hash($assessment),
                'lines' => $assessment->lines
                    ->sortBy('id')
                    ->values()
                    ->map(fn (AssessmentLine $line): array => [
                        'code' => $line->code,
                        'name' => $line->name,
                        'category' => $line->category->value,
                        'amount_cents' => $line->amount_cents,
                    ])
                    ->all(),
            ],
            'comparison' => [
                'delta_amount_cents' => $deltaAmountCents,
                'absolute_delta_amount_cents' => abs($deltaAmountCents),
                'direction' => match (true) {
                    $deltaAmountCents > 0 => 'new_bpls_higher',
                    $deltaAmountCents < 0 => 'legacy_source_higher',
                    default => 'equal',
                },
                'component_identity_mapping' => $metadata['component_identity_mapping'],
            ],
            'statement' => 'A difference is an audit signal for municipal review, not an error verdict. Legacy fee labels are not treated as current fee-policy identity.',
            'operational_effect' => false,
        ];
    }

    private function sourceEvidenceValid(mixed $historicalAssessment): bool
    {
        if (! is_array($historicalAssessment)
            || ! is_int($historicalAssessment['recorded_total_amount_cents'] ?? null)
            || ! is_int($historicalAssessment['component_total_amount_cents'] ?? null)
            || ! is_bool($historicalAssessment['source_internal_reconciles'] ?? null)
            || ! is_array($historicalAssessment['schedules'] ?? null)
            || ! is_string($historicalAssessment['source_evidence_hash'] ?? null)) {
            return false;
        }

        $payload = Arr::except($historicalAssessment, ['source_evidence_hash']);

        return hash_equals(
            $historicalAssessment['source_evidence_hash'],
            hash('sha256', json_encode(
                $this->normalize($payload),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
            )),
        );
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }
}
