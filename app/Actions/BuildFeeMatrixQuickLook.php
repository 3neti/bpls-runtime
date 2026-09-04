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
use Illuminate\Support\Str;

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
            'schema_version' => 'bpls.municipal-fee-matrix.v3',
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
            'service_category' => $this->serviceCategory($rule->code),
            'management_url' => route('staff.fee-rules.show', $rule, false),
        ];
    }

    /** @return array<string, mixed> */
    private function ordinancePayload(RevenueCodeProvision $provision): array
    {
        $entries = $provision->rows->map(fn (RevenueCodeProvisionRow $row): array => [
            'id' => 'row-'.$row->id,
            'code' => $row->code,
            'kind' => 'schedule_row',
            'service_label' => $this->rowServiceLabel($provision, $row),
            'basis_label' => $this->rowBasisLabel($row),
            'unit_label' => null,
            'source_text' => $row->source_value_text,
            'amount_minor' => $row->amount_cents,
            'rate_basis_points' => $row->rate_basis_points,
            'is_ceiling' => $row->is_ceiling,
            'status' => $row->normalization_status->value,
        ])->concat($provision->clauses
            ->map(fn (RevenueCodeProvisionClause $clause): array => [
                'id' => 'clause-'.$clause->id,
                'code' => $clause->code,
                'kind' => 'ordinance_clause',
                'service_label' => $this->clauseServiceLabel($provision, $clause),
                'basis_label' => $this->clauseBasisLabel($clause),
                'unit_label' => $this->clauseUnitLabel($clause),
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
            'service_category' => $this->serviceCategory($provision->code),
            'governance_url' => route('staff.fee-rules.index', absolute: false).'#revenue-code-provision-'.$provision->code,
            'linked_fee_rule' => $provision->feeRule ? [
                'id' => $provision->feeRule->id,
                'code' => $provision->feeRule->code,
                'execution_status' => $provision->feeRule->currentReconciliation?->execution_status->value,
                'management_url' => route('staff.fee-rules.show', $provision->feeRule, false),
            ] : null,
            'entries' => $entries->all(),
        ];
    }

    /** @return array{key: string, label: string} */
    private function serviceCategory(string $code): array
    {
        return match (true) {
            Str::startsWith($code, 'MRC-2A') => ['key' => 'business_taxes', 'label' => 'Business Taxes'],
            Str::startsWith($code, 'MRC-2B') => ['key' => 'markets_mobile_trade', 'label' => 'Markets, Peddlers & Mobile Trade'],
            Str::startsWith($code, 'MRC-2F') => ['key' => 'business_taxes', 'label' => 'Business Taxes'],
            Str::startsWith($code, 'MRC-3A-04') => ['key' => 'inspections_certificates', 'label' => 'Inspections & Certificates'],
            Str::startsWith($code, 'MRC-3A') => ['key' => 'business_permits', 'label' => "Mayor's Permit & Business Licensing"],
            Str::startsWith($code, ['MRC-3B', 'MRC-3C']) => ['key' => 'cockpit_events', 'label' => 'Cockpit & Special Events'],
            Str::startsWith($code, ['MRC-3E', 'MRC-3J']) => ['key' => 'special_permits', 'label' => 'Filming, Parades & Special Permits'],
            Str::startsWith($code, ['MRC-3D', 'MRC-3F']) => ['key' => 'animals_agriculture', 'label' => 'Animals & Agricultural Services'],
            Str::startsWith($code, 'MRC-3G') => ['key' => 'public_works', 'label' => 'Street Excavation & Public Works'],
            Str::startsWith($code, ['MRC-3H', 'MRC-3I']) => ['key' => 'weights_measures', 'label' => 'Weights, Measures & Fuel Pumps'],
            Str::startsWith($code, 'MRC-3K') => ['key' => 'equipment', 'label' => 'Equipment & Machinery'],
            Str::startsWith($code, 'MRC-3L') => ['key' => 'transport', 'label' => 'Transport & Tricycle Services'],
            default => ['key' => 'other', 'label' => 'Other Municipal Services'],
        };
    }

    private function clauseServiceLabel(RevenueCodeProvision $provision, RevenueCodeProvisionClause $clause): string
    {
        $metadata = $clause->metadata ?? [];
        $businessCategory = data_get($metadata, 'business_category');
        if (is_string($businessCategory) && $businessCategory !== '') {
            return $businessCategory;
        }

        $candidate = data_get($metadata, 'candidate_instrument_class')
            ?? data_get($metadata, 'candidate_charge')
            ?? data_get($metadata, 'candidate_equipment_class')
            ?? data_get($metadata, 'candidate_vehicle_class')
            ?? data_get($metadata, 'candidate_charge_object');

        if (is_string($candidate) && $candidate !== '') {
            return match ($candidate) {
                'apothecary_balance_of_precision' => 'Apothecary balance',
                'steelyard_or_espada_scale' => 'Steelyard / Espada scale',
                default => Str::of($candidate)->replace('_', ' ')->headline()->toString(),
            };
        }

        if (is_array(data_get($metadata, 'candidate_service'))) {
            return collect(data_get($metadata, 'candidate_service'))
                ->map(fn (mixed $service): string => Str::of((string) $service)->replace('_', ' ')->headline()->toString())
                ->join(' & ');
        }

        return $provision->title;
    }

    private function rowServiceLabel(RevenueCodeProvision $provision, RevenueCodeProvisionRow $row): string
    {
        $segments = preg_split('/\s+[—–]\s+/', $row->source_basis_text) ?: [];

        return count($segments) > 1 ? trim($segments[0]) : $provision->title;
    }

    private function rowBasisLabel(RevenueCodeProvisionRow $row): string
    {
        $segments = preg_split('/\s+[—–]\s+/', $row->source_basis_text) ?: [];
        if (count($segments) > 1) {
            array_shift($segments);
        }

        $basis = implode(' — ', array_filter(
            $segments,
            fn (string $segment): bool => preg_match('/^P(?:HP)?\s*[\d,.]+\.?$/i', trim($segment)) !== 1,
        ));

        return trim($basis !== '' ? $basis : $row->source_basis_text);
    }

    private function clauseBasisLabel(RevenueCodeProvisionClause $clause): string
    {
        $metadata = $clause->metadata ?? [];
        $classification = data_get($metadata, 'source_classification');
        if (is_string($classification) && $classification !== '') {
            return $classification;
        }

        if (data_get($metadata, 'candidate_instrument_class') === 'bronze_wire_seal') {
            return 'Annual sealing and licensing';
        }

        $minimum = data_get($metadata, 'candidate_capacity_min_grams');
        $maximum = data_get($metadata, 'candidate_capacity_max_grams');

        if (is_int($minimum) || is_int($maximum)) {
            $useGrams = data_get($metadata, 'candidate_instrument_class') === 'apothecary_balance_of_precision';
            $format = fn (int $grams): string => $useGrams
                ? number_format($grams).' g'
                : number_format($grams / 1_000).' kg';

            return match (true) {
                is_int($minimum) && is_int($maximum) => 'Over '.$format($minimum).' to '.$format($maximum),
                is_int($minimum) => 'Over '.$format($minimum),
                default => 'Up to '.$format($maximum),
            };
        }

        $examples = data_get($metadata, 'candidate_examples');
        if (is_array($examples) && $examples !== []) {
            return collect($examples)
                ->map(fn (mixed $example): string => Str::of((string) $example)->replace('_', ' ')->headline()->toString())
                ->join(', ');
        }

        if (data_get($metadata, 'candidate_location') === 'outside_office') {
            return 'Outside the municipal office';
        }

        $withoutAmount = preg_replace('/\s*[-–]\s*P(?:HP)?\s*[\d,.]+.*$/i', '', $clause->source_text) ?? $clause->source_text;

        return Str::of($withoutAmount)->after(':')->trim()->finish('.')->toString();
    }

    private function clauseUnitLabel(RevenueCodeProvisionClause $clause): ?string
    {
        $metadata = $clause->metadata ?? [];

        if (data_get($metadata, 'candidate_charge_object') === 'seal') {
            return 'Per seal';
        }

        if (data_get($metadata, 'candidate_instrument_class') !== null) {
            return 'Per instrument';
        }

        $unit = data_get($metadata, 'candidate_unit');

        return match ($unit) {
            'instrument' => 'Per instrument',
            'equipment_item_per_annum' => 'Per item / year',
            null => null,
            default => Str::of((string) $unit)->replace('_', ' ')->headline()->toString(),
        };
    }
}
