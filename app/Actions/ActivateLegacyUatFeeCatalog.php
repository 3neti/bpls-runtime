<?php

namespace App\Actions;

use App\Enums\FeeRuleExecutionStatus;
use App\Enums\LegacyFeeRuleReconciliationStatus;
use App\Enums\LegacyLineOfBusinessReconciliationStatus;
use App\Enums\UserPermission;
use App\Models\FeeRule;
use App\Models\FeeRuleAuditEvent;
use App\Models\LegacyFeeRuleReconciliation;
use App\Models\LegacyImportBatch;
use App\Models\LegacyLineOfBusinessReconciliation;
use App\Models\LineOfBusiness;
use App\Models\RevenueCodeProvision;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

final class ActivateLegacyUatFeeCatalog
{
    public function __construct(
        private readonly ReconcileLegacyFeeCatalogCandidate $reconcileCandidate,
    ) {}

    /** @return array<string, int|string> */
    public function handle(LegacyImportBatch $batch, string $effectiveFrom, User $actor): array
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Legacy Price List activation is restricted to the Laboratory and UAT.');
        }
        if (! $actor->can(UserPermission::ManageFeeRules->value)) {
            throw new LogicException('Legacy Price List activation requires Price List management authority.');
        }

        $effectiveDate = Carbon::createFromFormat('!Y-m-d', $effectiveFrom);
        if ($effectiveDate === null || $effectiveDate->format('Y-m-d') !== $effectiveFrom) {
            throw new LogicException('The effective date must use YYYY-MM-DD.');
        }

        $candidates = LegacyFeeRuleReconciliation::query()
            ->where('legacy_source_id', $batch->legacy_source_id)
            ->where('source_dataset', 'fees')
            ->orderBy('id')
            ->get();
        if ($candidates->isEmpty()) {
            throw new LogicException('Characterize the staged legacy fee catalogue before activation.');
        }

        $operational = $candidates->filter(fn (LegacyFeeRuleReconciliation $candidate): bool => $this->isOperational($candidate));
        $excludedSeed = $candidates->filter(fn (LegacyFeeRuleReconciliation $candidate): bool => data_get($candidate->metadata, 'applicability_scope') === 'application_wide');
        $excludedTest = $candidates->diff($operational)->diff($excludedSeed);

        return DB::transaction(function () use ($batch, $effectiveFrom, $actor, $operational, $excludedSeed, $excludedTest): array {
            $deactivated = FeeRule::query()
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->where('code', 'like', 'MRC-%')
                        ->orWhere('code', 'like', 'LAB-NELSON-%');
                })
                ->update(['is_active' => false]);
            $supersededProvisions = 0;
            RevenueCodeProvision::query()->each(function (RevenueCodeProvision $provision) use (&$supersededProvisions): void {
                $metadata = $provision->metadata ?? [];
                if (data_get($metadata, 'catalog_status') === 'superseded_by_ipil_legacy_uat_v1') {
                    return;
                }
                $provision->forceFill(['metadata' => [
                    ...$metadata,
                    'catalog_status' => 'superseded_by_ipil_legacy_uat_v1',
                ]])->save();
                $supersededProvisions++;
            });

            $lineOfBusinessIds = [];
            $officeCounts = ['health' => 0, 'menro' => 0, 'assessor' => 0];
            foreach ($operational as $candidate) {
                if ($candidate->status === LegacyFeeRuleReconciliationStatus::Rejected) {
                    throw new LogicException('A rejected legacy fee candidate cannot be activated.');
                }

                if ($candidate->fee_rule_id === null) {
                    $candidate = $this->reconcileCandidate->handle(
                        $candidate,
                        'create_proposed',
                        null,
                        'Accepted as the initial Laboratory/UAT Price List from the migrated Ipil catalogue.',
                        $actor,
                    );
                }

                $feeRule = FeeRule::query()->with('currentReconciliation')->findOrFail($candidate->fee_rule_id);
                $configuredGroupNames = data_get($candidate->metadata, 'applicable_group_names', []);
                $groupNames = collect(is_array($configuredGroupNames) ? $configuredGroupNames : [])
                    ->filter(fn (mixed $name): bool => is_string($name) && trim($name) !== '' && ! $this->isTestText($name))
                    ->unique()
                    ->values();
                $mappedIds = $groupNames->map(function (string $name) use ($batch, $actor, &$lineOfBusinessIds): int {
                    $normalized = str($name)->squish()->lower()->toString();
                    $line = LineOfBusiness::query()->firstOrCreate(
                        ['code' => 'IPIL-LEGACY-LOB-'.strtoupper(substr(hash('sha256', $normalized), 0, 16))],
                        [
                            'name' => trim($name),
                            'major_category' => 'Migrated Ipil catalogue',
                            'is_active' => true,
                            'metadata' => [
                                'catalog_version' => 'ipil-legacy-uat-v1',
                                'source_name_sha256' => hash('sha256', $normalized),
                                'source_batch_id' => $batch->id,
                            ],
                        ],
                    );
                    $lineOfBusinessIds[$line->id] = true;
                    LegacyLineOfBusinessReconciliation::query()
                        ->where('legacy_source_id', $batch->legacy_source_id)
                        ->where('source_value_hash', hash('sha256', $normalized))
                        ->update([
                            'line_of_business_id' => $line->id,
                            'status' => LegacyLineOfBusinessReconciliationStatus::Accepted->value,
                            'decision_authority' => $actor->name,
                            'decided_at' => now(),
                        ]);

                    return $line->id;
                })->all();

                $officeCode = $this->officeCode((string) data_get($candidate->metadata, 'name'));
                $selection = $officeCode === null
                    ? 'treasury_lob_determination_only'
                    : 'concerned_office_payment_order_only';
                $metadata = $feeRule->metadata ?? [];
                $feeRule->forceFill([
                    'line_of_business_id' => $mappedIds[0] ?? null,
                    'effective_from' => $effectiveFrom,
                    'effective_until' => null,
                    'is_active' => true,
                    'legal_basis' => 'Migrated Ipil operational Price List accepted for Laboratory/UAT use.',
                    'metadata' => [
                        ...$metadata,
                        'catalog_status' => 'active_uat',
                        'catalog_version' => 'ipil-legacy-uat-v1',
                        'assessment_selection' => $selection,
                        'manual_amount_required' => $feeRule->calculation_type->value !== 'fixed',
                        'responsible_office_code' => $officeCode,
                        'reconciliation_required' => true,
                        'price_list_source_classification' => 'migrated_legacy_uat',
                    ],
                ])->save();
                $feeRule->lineOfBusinesses()->sync(collect($mappedIds)->mapWithKeys(fn (int $id): array => [
                    $id => ['source' => 'legacy_catalogue', 'metadata' => json_encode(['catalog_version' => 'ipil-legacy-uat-v1'], JSON_THROW_ON_ERROR)],
                ])->all());
                $feeRule->officeAssignments()->delete();
                if ($officeCode !== null) {
                    $feeRule->officeAssignments()->create([
                        'office_code' => $officeCode,
                        'office_label' => $this->officeLabel($officeCode),
                        'source' => 'legacy_fee_nomenclature',
                        'metadata' => ['catalog_version' => 'ipil-legacy-uat-v1'],
                    ]);
                    $officeCounts[$officeCode]++;
                }

                $reconciliation = $feeRule->currentReconciliation;
                $reconciliation?->forceFill([
                    'legal_authority' => 'Laboratory/UAT acceptance of the migrated Ipil operational configuration',
                    'normalized_interpretation' => 'The legacy rule is selectable as a municipal line item. Range and formula amounts require explicit officer or Treasury determination.',
                    'decision_authority' => $actor->name,
                    'decision_reference' => 'ipil-legacy-uat-v1',
                    'effective_from' => $effectiveFrom,
                    'execution_status' => FeeRuleExecutionStatus::Executable,
                    'execution_reason' => 'Executable through explicit municipal line-item determination in Laboratory/UAT.',
                    'decided_at' => now(),
                ])->save();
                $candidate->forceFill([
                    'status' => LegacyFeeRuleReconciliationStatus::Accepted,
                    'decision_authority' => $actor->name,
                    'evidence_reference' => 'ipil-legacy-uat-v1',
                    'decided_at' => now(),
                    'metadata' => [
                        ...($candidate->metadata ?? []),
                        'review_disposition' => 'activated_for_uat',
                        'review_reason' => 'Accepted as the initial Laboratory/UAT Price List.',
                        'reviewed_by_id' => $actor->id,
                        'reviewed_at' => now()->toIso8601String(),
                        'executable' => true,
                    ],
                ])->save();
                FeeRuleAuditEvent::query()->create([
                    'fee_rule_id' => $feeRule->id,
                    'event' => 'legacy_uat_catalog_activated',
                    'actor_id' => $actor->id,
                    'occurred_at' => now(),
                    'snapshot' => [
                        'catalog_version' => 'ipil-legacy-uat-v1',
                        'effective_from' => $effectiveFrom,
                        'line_of_business_count' => count($mappedIds),
                        'responsible_office_code' => $officeCode,
                    ],
                ]);
            }

            $this->exclude($excludedSeed, 'Repeated unscoped legacy seed catalogue', $actor);
            $this->exclude($excludedTest, 'Legacy test fixture', $actor);

            return [
                'catalog_version' => 'ipil-legacy-uat-v1',
                'effective_from' => $effectiveFrom,
                'source_candidate_count' => $operational->count() + $excludedSeed->count() + $excludedTest->count(),
                'activated_fee_rule_count' => $operational->count(),
                'created_line_of_business_count' => count($lineOfBusinessIds),
                'excluded_unscoped_seed_count' => $excludedSeed->count(),
                'excluded_test_fixture_count' => $excludedTest->count(),
                'deactivated_placeholder_count' => $deactivated,
                'superseded_ordinance_provision_count' => $supersededProvisions,
                'health_fee_count' => $officeCounts['health'],
                'menro_fee_count' => $officeCounts['menro'],
                'assessor_fee_count' => $officeCounts['assessor'],
            ];
        });
    }

    private function isOperational(LegacyFeeRuleReconciliation $candidate): bool
    {
        if (data_get($candidate->metadata, 'applicability_scope') === 'application_wide') {
            return false;
        }

        return ! $this->isTestText((string) data_get($candidate->metadata, 'name'))
            && ! $this->isTestText((string) data_get($candidate->metadata, 'division_name'));
    }

    private function isTestText(string $value): bool
    {
        return str_contains(strtolower($value), 'test');
    }

    private function officeCode(string $name): ?string
    {
        return match ($name) {
            'Health Certificate', 'Sanitary Permit Fee' => 'health',
            'Solid Waste Management' => 'menro',
            'Weight & Measure' => 'assessor',
            default => null,
        };
    }

    private function officeLabel(string $officeCode): string
    {
        return match ($officeCode) {
            'health' => 'Municipal Health Office',
            'menro' => 'MENRO',
            'assessor' => 'Municipal Assessor',
            default => str($officeCode)->headline()->toString(),
        };
    }

    /** @param iterable<int, LegacyFeeRuleReconciliation> $candidates */
    private function exclude(iterable $candidates, string $reason, User $actor): void
    {
        foreach ($candidates as $candidate) {
            if ($candidate->status === LegacyFeeRuleReconciliationStatus::Accepted) {
                continue;
            }
            $candidate->forceFill([
                'status' => LegacyFeeRuleReconciliationStatus::Rejected,
                'decision_authority' => $actor->name,
                'evidence_reference' => 'ipil-legacy-uat-v1',
                'decided_at' => now(),
                'metadata' => [
                    ...($candidate->metadata ?? []),
                    'review_disposition' => 'excluded_from_operational_catalog',
                    'review_reason' => $reason,
                    'reviewed_by_id' => $actor->id,
                    'reviewed_at' => now()->toIso8601String(),
                    'executable' => false,
                ],
            ])->save();
        }
    }
}
