<?php

namespace App\Evaluation;

use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationItemType;
use App\Enums\BusinessPermitEvaluationSource;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationVersion;

class BusinessPermitEvaluationReadiness
{
    public function __construct(private readonly BusinessPermitEvaluationResolver $resolver) {}

    /**
     * @return array{ready: bool, mode: string, issues: list<string>, projection: array<string, mixed>}
     */
    public function forAssessment(BusinessPermitEvaluation $evaluation, string $mode = 'commissioned'): array
    {
        $version = $evaluation->currentVersion;

        if (! $version instanceof BusinessPermitEvaluationVersion) {
            return ['ready' => false, 'mode' => $mode, 'issues' => ['Evaluation has no current version.'], 'projection' => []];
        }

        $projection = $this->resolver->resolve($evaluation, $version);
        $issues = [];

        if ($evaluation->permitApplication->submitted_at === null) {
            $issues[] = 'Required applicant facts are not lodged because the permit application is not submitted.';
        }

        if ($projection['resolved_line_of_business_ids'] === []) {
            $issues[] = 'At least one valid resolved Line of Business is required.';
        }

        foreach (data_get($projection, 'application.declared_lines', []) as $line) {
            if (($line['line_of_business_id'] ?? null) === null
                || ! is_int($line['declared_gross_sales_cents'] ?? null)
                || ($line['declared_gross_sales_cents'] ?? -1) < 0
                || ! is_int($line['capital_investment_cents'] ?? null)
                || ($line['capital_investment_cents'] ?? -1) < 0) {
                $issues[] = 'Required applicant financial declarations are incomplete or invalid.';
            }
        }

        foreach ($projection['items'] as $item) {
            if (($item['is_required'] ?? false) && $item['applicability'] === BusinessPermitEvaluationApplicability::Undetermined->value) {
                $issues[] = "Required applicability is undetermined for [{$item['key']}].";
            }

            if (($item['is_required'] ?? false) && $item['resolution'] !== 'resolved') {
                $issues[] = "Required item [{$item['key']}] is {$item['resolution']}.";
            }

            if ($item['applicability'] === BusinessPermitEvaluationApplicability::Applicable->value
                && data_get($item, 'metadata.inspection_required') === true
                && data_get($item, 'value.inspection.completed') !== true) {
                $issues[] = "Required inspection/review is incomplete for [{$item['key']}].";
            }

            if (data_get($item, 'metadata.targeted_return_unresolved') === true) {
                $issues[] = "Targeted return remains unresolved for [{$item['key']}].";
            }

            if ($item['item_type'] !== BusinessPermitEvaluationItemType::Charge->value
                || $item['applicability'] !== BusinessPermitEvaluationApplicability::Applicable->value) {
                continue;
            }

            $amount = data_get($item, 'value.amount_cents');
            if (! is_int($amount) || $amount < 0) {
                $issues[] = "Applicable charge [{$item['key']}] has no resolved non-negative amount; undefined is not zero.";
            }

            if (data_get($item, 'metadata.fee_rule_id') !== null) {
                $issues[] = "Charge [{$item['key']}] duplicates the canonical FeeRule path.";
            }

            $source = BusinessPermitEvaluationSource::tryFrom((string) ($item['source_classification'] ?? ''));
            if ($mode === 'commissioned' && ! $source?->isCommissionedChargeSource()) {
                $issues[] = "Applicable charge [{$item['key']}] has no accepted commissioned source or procedure.";
            }
            if ($mode === 'commissioned' && $source === BusinessPermitEvaluationSource::ProvisionalUat) {
                $issues[] = "Applicable charge [{$item['key']}] is provisional_uat and cannot establish production liability.";
            }
        }

        foreach ($projection['pricing_issues'] as $issue) {
            $issues[] = 'Selected pricing rule is blocked or ambiguous: '.$issue;
        }

        if (! $projection['fingerprint_current']) {
            $issues[] = 'Evaluation fingerprint is stale and must be refreshed before assessment.';
        }

        if ($version->counterCheck === null) {
            $issues[] = 'Required Treasury counter-check is not complete for the current Evaluation version.';
        }

        return [
            'ready' => $issues === [],
            'mode' => $mode,
            'issues' => array_values(array_unique($issues)),
            'projection' => $projection,
        ];
    }
}
