<?php

namespace App\Actions;

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleExecutionStatus;
use App\Enums\FeeRulePublicationSource;
use App\Enums\FeeRuleScope;
use App\Enums\RevenueCodeProvisionType;
use App\Models\FeeRule;
use App\Models\FeeRuleReconciliation;
use App\Models\RevenueCodeProvision;
use App\Models\RevenueCodeProvisionClause;
use App\Models\RevenueCodeProvisionRow;
use Illuminate\Support\Collection;

final class BuildFeeMatrixQuickLook
{
    /** @return array<string, mixed> */
    public function handle(
        ?string $search = null,
        ?string $office = null,
        ?int $lineOfBusinessId = null,
        ?int $feeRuleId = null,
        ?string $chargeCode = null,
        ?string $chargeLabel = null,
        ?string $sourceClassification = null,
    ): array {
        $allRules = FeeRule::query()
            ->with(['lineOfBusiness', 'currentReconciliation'])
            ->where('is_active', true)
            ->whereNull('metadata->scenario_id')
            ->where('code', 'not like', 'SCENARIO-%')
            ->where('code', 'not like', 'EVAL-UAT-%')
            ->when($search, fn ($query, string $value) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$value}%")
                ->orWhere('name', 'like', "%{$value}%")
                ->orWhereHas('lineOfBusiness', fn ($query) => $query->where('name', 'like', "%{$value}%"))))
            ->orderBy('scope')->orderBy('code')->get()
            ->filter(fn (FeeRule $rule): bool => ! in_array(data_get($rule->metadata, 'semantic_classification'), [
                'synthetic', 'provisional_uat', 'historical', 'mock', 'legacy_evidence_only', 'lifecycle_test', 'test',
            ], true));

        $rules = $allRules
            ->when($feeRuleId !== null, fn (Collection $rules) => $rules->where('id', $feeRuleId))
            ->when($feeRuleId === null && $office !== null, fn (Collection $rules) => $rules->filter(
                fn (FeeRule $rule): bool => data_get($rule->metadata, 'responsible_office') === null
                    || data_get($rule->metadata, 'responsible_office') === $office,
            ))
            ->when($feeRuleId === null && $lineOfBusinessId !== null, fn (Collection $rules) => $rules->where('line_of_business_id', $lineOfBusinessId));

        $ordinanceRegister = RevenueCodeProvision::query()
            ->with(['feeRule.currentReconciliation', 'rows', 'clauses'])
            ->whereIn('provision_type', [
                RevenueCodeProvisionType::FixedFee->value,
                RevenueCodeProvisionType::TaxSchedule->value,
                RevenueCodeProvisionType::PercentageRate->value,
                RevenueCodeProvisionType::PresumptiveIncomeSchedule->value,
            ])
            ->orderBy('section_reference')
            ->orderBy('code')
            ->get();

        return [
            'schema_version' => 'bpls.municipal-fee-matrix.v2',
            'currency' => 'PHP',
            'read_only' => true,
            'context' => [
                'charge_code' => $chargeCode,
                'charge_label' => $chargeLabel,
                'fee_rule_id' => $feeRuleId,
                'source_classification' => $sourceClassification,
                'has_direct_fee_rule' => $feeRuleId !== null && $allRules->contains('id', $feeRuleId),
            ],
            'summary' => [
                'fee_rules' => $allRules->count(),
                'executable_fee_rules' => $allRules->filter(
                    fn (FeeRule $rule): bool => $rule->currentReconciliation?->execution_status === FeeRuleExecutionStatus::Executable,
                )->count(),
                'ordinance_fee_provisions' => $ordinanceRegister->count(),
                'ordinance_provisions' => RevenueCodeProvision::query()->count(),
                'ordinance_schedule_rows' => RevenueCodeProvisionRow::query()->count(),
                'ordinance_policy_clauses' => RevenueCodeProvisionClause::query()->count(),
            ],
            'application_wide' => $rules->where('scope', FeeRuleScope::Application)->map($this->payload(...))->values()->all(),
            'line_of_businesses' => $rules->where('scope', FeeRuleScope::LineOfBusiness)
                ->groupBy(fn (FeeRule $rule): string => (string) $rule->line_of_business_id)
                ->map(fn (Collection $group): array => [
                    'id' => $group->first()?->line_of_business_id,
                    'name' => $group->first()?->lineOfBusiness->name ?? 'Unclassified Line of Business',
                    'fees' => $group->map($this->payload(...))->values()->all(),
                ])->values()->all(),
            'ordinance_register' => $ordinanceRegister->map($this->ordinancePayload(...))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function payload(FeeRule $rule): array
    {
        $source = FeeRulePublicationSource::forRule($rule);
        $reconciliation = $rule->currentReconciliation;
        $inForce = $reconciliation instanceof FeeRuleReconciliation
            && $reconciliation->execution_status === FeeRuleExecutionStatus::Executable;
        $status = match (true) {
            data_get($rule->metadata, 'amount_determined_by_concerned_office') === true => 'concerned_office_determined',
            $inForce => 'in_force',
            $source === FeeRulePublicationSource::MunicipalConfirmationRequired => 'municipal_confirmation_required',
            default => 'not_commissioned',
        };
        $mayShowAmount = $rule->calculation_type === FeeRuleCalculationType::Fixed
            && in_array($status, ['in_force', 'municipal_confirmation_required'], true);

        return [
            'id' => $rule->id,
            'code' => $rule->code,
            'name' => $rule->name,
            'family' => $rule->scope === FeeRuleScope::Application ? 'application_wide' : 'line_of_business',
            'line_of_business_id' => $rule->line_of_business_id,
            'line_of_business_name' => $rule->lineOfBusiness?->name,
            'responsible_office' => data_get($rule->metadata, 'responsible_office'),
            'currency' => 'PHP',
            'amount_minor' => $mayShowAmount ? $rule->amount_cents : null,
            'status' => $status,
            'effective_from' => $rule->effective_from->toDateString(),
            'effective_until' => $rule->effective_until?->toDateString(),
            'legal_basis' => $rule->legal_basis,
            'version' => $reconciliation?->version,
        ];
    }

    /** @return array<string, mixed> */
    private function ordinancePayload(RevenueCodeProvision $provision): array
    {
        $entries = $provision->rows->map(fn (RevenueCodeProvisionRow $row): array => [
            'id' => 'row-'.$row->id,
            'kind' => 'schedule_row',
            'label' => $row->source_basis_text,
            'source_text' => $row->source_value_text,
            'amount_minor' => $row->amount_cents,
            'rate_basis_points' => $row->rate_basis_points,
            'is_ceiling' => $row->is_ceiling,
            'status' => $row->normalization_status->value,
        ])->concat($provision->clauses
            ->map(fn (RevenueCodeProvisionClause $clause): array => [
                'id' => 'clause-'.$clause->id,
                'kind' => 'ordinance_clause',
                'label' => str($clause->clause_type->value)->headline()->toString(),
                'source_text' => $clause->source_text,
                'amount_minor' => $clause->amount_cents,
                'rate_basis_points' => $clause->rate_basis_points,
                'is_ceiling' => $clause->is_ceiling,
                'status' => $clause->reconciliation_status->value,
            ]))->values();

        return [
            'id' => $provision->id,
            'code' => $provision->code,
            'section_reference' => $provision->section_reference,
            'title' => $provision->title,
            'provision_type' => $provision->provision_type->value,
            'evidence_summary' => $provision->evidence_summary,
            'reconciliation_status' => $provision->reconciliation_status->value,
            'reconciliation_notes' => $provision->reconciliation_notes,
            'known_ambiguities' => data_get($provision->metadata, 'known_ambiguities', []),
            'linked_fee_rule' => $provision->feeRule ? [
                'id' => $provision->feeRule->id,
                'code' => $provision->feeRule->code,
                'execution_status' => $provision->feeRule->currentReconciliation?->execution_status->value,
            ] : null,
            'entries' => $entries->all(),
        ];
    }
}
