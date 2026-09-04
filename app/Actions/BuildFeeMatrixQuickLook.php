<?php

namespace App\Actions;

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleExecutionStatus;
use App\Enums\FeeRulePublicationSource;
use App\Enums\FeeRuleScope;
use App\Models\FeeRule;
use App\Models\FeeRuleReconciliation;
use Illuminate\Support\Collection;

final class BuildFeeMatrixQuickLook
{
    /** @return array<string, mixed> */
    public function handle(?string $search = null, ?string $office = null, ?int $lineOfBusinessId = null): array
    {
        $rules = FeeRule::query()
            ->with(['lineOfBusiness', 'currentReconciliation'])
            ->where('is_active', true)
            ->whereNull('metadata->scenario_id')
            ->where('code', 'not like', 'SCENARIO-%')
            ->where('code', 'not like', 'EVAL-UAT-%')
            ->when($search, fn ($query, string $value) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$value}%")
                ->orWhere('name', 'like', "%{$value}%")
                ->orWhereHas('lineOfBusiness', fn ($query) => $query->where('name', 'like', "%{$value}%"))))
            ->when($office, fn ($query, string $value) => $query->where('metadata->responsible_office', $value))
            ->when($lineOfBusinessId, fn ($query, int $value) => $query->where('line_of_business_id', $value))
            ->orderBy('scope')->orderBy('code')->get()
            ->filter(fn (FeeRule $rule): bool => ! in_array(data_get($rule->metadata, 'semantic_classification'), [
                'synthetic', 'provisional_uat', 'historical', 'mock', 'legacy_evidence_only', 'lifecycle_test', 'test',
            ], true));

        return [
            'schema_version' => 'bpls.municipal-fee-matrix.v1',
            'currency' => 'PHP',
            'read_only' => true,
            'application_wide' => $rules->where('scope', FeeRuleScope::Application)->map($this->payload(...))->values()->all(),
            'line_of_businesses' => $rules->where('scope', FeeRuleScope::LineOfBusiness)
                ->groupBy(fn (FeeRule $rule): string => (string) $rule->line_of_business_id)
                ->map(fn (Collection $group): array => [
                    'id' => $group->first()?->line_of_business_id,
                    'name' => $group->first()?->lineOfBusiness->name ?? 'Unclassified Line of Business',
                    'fees' => $group->map($this->payload(...))->values()->all(),
                ])->values()->all(),
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
}
