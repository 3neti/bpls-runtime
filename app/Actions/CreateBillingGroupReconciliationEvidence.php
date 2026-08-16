<?php

namespace App\Actions;

use App\Enums\BillingGroupReconciliationStatus;
use App\Models\BillingGroup;
use App\Models\BillingGroupField;
use App\Models\BillingGroupReconciliation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateBillingGroupReconciliationEvidence
{
    /**
     * @param  array{evidence_type: string, evidence_reference: string, source_excerpt?: string|null, operational_interpretation?: string|null, unresolved_questions: list<string>}  $data
     * @param  array<string, mixed>  $provenance
     */
    public function handle(BillingGroup $billingGroup, User $actor, array $data, array $provenance = []): BillingGroupReconciliation
    {
        return DB::transaction(function () use ($billingGroup, $actor, $data, $provenance): BillingGroupReconciliation {
            $lockedBillingGroup = BillingGroup::query()
                ->with('fields')
                ->lockForUpdate()
                ->whereKey($billingGroup->id)
                ->sole();
            $version = ((int) $lockedBillingGroup->reconciliations()->max('version')) + 1;

            return $lockedBillingGroup->reconciliations()->create([
                'recorded_by_id' => $actor->id,
                'version' => $version,
                'evidence_type' => $data['evidence_type'],
                'evidence_reference' => $data['evidence_reference'],
                'source_excerpt' => $data['source_excerpt'] ?? null,
                'operational_interpretation' => $data['operational_interpretation'] ?? null,
                'unresolved_questions' => $data['unresolved_questions'],
                'reconciliation_status' => BillingGroupReconciliationStatus::PendingMunicipalDecision,
                'execution_status' => 'blocked',
                'execution_reason' => 'Evidence has been recorded, but no authorized municipal decision accepts this billing group or establishes executable financial policy.',
                'definition_snapshot' => [
                    'billing_group_id' => $lockedBillingGroup->id,
                    'name' => $lockedBillingGroup->name,
                    'acceptance_status' => $lockedBillingGroup->acceptance_status->value,
                    'is_active' => $lockedBillingGroup->is_active,
                    'fields' => $lockedBillingGroup->fields->map(fn (BillingGroupField $field): array => [
                        'field_id' => $field->id,
                        'key' => $field->key,
                        'name' => $field->name,
                        'field_type' => $field->field_type->value,
                        'is_required' => $field->is_required,
                        'is_unique' => $field->is_unique,
                        'sort_order' => $field->sort_order,
                        'options' => $field->options,
                        'default_value' => $field->default_value,
                    ])->values()->all(),
                ],
                'metadata' => [
                    ...$provenance,
                    'action' => self::class,
                    'actor_id' => $actor->id,
                    'financial_effect' => 'none',
                    'acceptance_effect' => 'none',
                ],
            ]);
        });
    }
}
