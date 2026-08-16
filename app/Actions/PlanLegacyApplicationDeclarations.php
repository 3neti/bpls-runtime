<?php

namespace App\Actions;

use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\LegacyApplicationIdMapping;
use App\Models\LegacyDeclarationLineMapping;
use App\Models\LegacyDeclarationMappingPlan;
use App\Models\LegacyImportBatch;
use App\Models\LegacyLineOfBusinessReconciliation;
use App\Models\LegacyRecord;
use App\Models\LineOfBusiness;
use App\Models\PermitApplicationLine;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PlanLegacyApplicationDeclarations
{
    public const PlannerVersion = 'bpls.declaration-mapping-plan.v1';

    public function __construct(private LegacyApplicationDeclarationProjector $projector) {}

    public function handle(LegacyImportBatch $batch, string $runReference): LegacyDeclarationMappingPlan
    {
        $this->assertReady($batch, $runReference);
        $datasetKey = $this->datasetKey($batch);
        $snapshot = $this->snapshotHash($batch, $datasetKey);
        $plan = DB::transaction(function () use ($batch, $runReference, $snapshot, $datasetKey): LegacyDeclarationMappingPlan {
            $existing = $batch->declarationMappingPlans()->where('run_reference', $runReference)->lockForUpdate()->first();

            if ($existing instanceof LegacyDeclarationMappingPlan) {
                if (! hash_equals($existing->dependency_snapshot_hash, $snapshot)) {
                    throw new RuntimeException("Declaration mapping plan run reference [{$runReference}] is bound to different source or reconciliation evidence.");
                }

                return $existing;
            }

            return $batch->declarationMappingPlans()->create([
                'run_reference' => $runReference, 'planner_version' => self::PlannerVersion,
                'dependency_snapshot_hash' => $snapshot, 'status' => LegacyMappingPlanStatus::Planning,
                'started_at' => now(), 'metadata' => ['application_dataset_key' => $datasetKey, 'domain_writes' => false, 'financial_calculations' => false],
            ]);
        });

        if (in_array($plan->status, [LegacyMappingPlanStatus::Planned, LegacyMappingPlanStatus::PlannedWithExceptions], true)) {
            return $this->evidence($plan);
        }

        foreach ($batch->records()->where('dataset_key', $datasetKey)->orderBy('id')->cursor() as $record) {
            $lines = $record->payload['linesOfBusiness'] ?? [];

            if (! is_array($lines)) {
                continue;
            }

            foreach (array_keys(array_values($lines)) as $index) {
                $this->planLine($plan, $record, $index);
            }
        }

        $ready = $plan->proposals()->where('status', LegacyMappingProposalStatus::Ready)->count();
        $review = $plan->proposals()->where('status', LegacyMappingProposalStatus::ReviewRequired)->count();
        $blocked = $plan->proposals()->where('status', LegacyMappingProposalStatus::Blocked)->count();
        $plan->update([
            'status' => $review + $blocked > 0 ? LegacyMappingPlanStatus::PlannedWithExceptions : LegacyMappingPlanStatus::Planned,
            'proposal_count' => $ready + $review + $blocked, 'ready_count' => $ready, 'review_count' => $review, 'blocked_count' => $blocked,
            'completed_at' => now(), 'metadata' => [...($plan->metadata ?? []), 'payloads_in_report' => false, 'permit_application_line_writes' => false],
        ]);

        return $this->evidence($plan);
    }

    private function planLine(LegacyDeclarationMappingPlan $plan, LegacyRecord $record, int $index): void
    {
        $projection = $this->projector->project($record, $index);

        $plan->proposals()->updateOrCreate(
            ['legacy_record_id' => $record->id, 'line_index' => $index],
            [
                'legacy_line_of_business_reconciliation_id' => $projection['reconciliation']?->id,
                'line_of_business_id' => $projection['line_of_business']?->id,
                'status' => $projection['status'],
                'projection_hash' => $this->projector->hashCanonical($projection['attributes']),
                'reasons' => $projection['reasons'],
                'metadata' => [
                    'legacy_application_id_sha256' => hash('sha256', $record->legacy_id),
                    'business_category_sha256' => $projection['category_hash'],
                    'projected_capital_cents' => $projection['capital_cents'],
                    'projected_gross_sales_cents' => $projection['gross_sales_cents'],
                    'financial_calculations' => false,
                    'domain_writes' => false,
                ],
            ],
        );
    }

    private function assertReady(LegacyImportBatch $batch, string $runReference): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy declaration planning is restricted to local and testing environments.');
        }

        if (! in_array($batch->status, [LegacyImportBatchStatus::Staged, LegacyImportBatchStatus::StagedWithExceptions], true)) {
            throw new RuntimeException('Legacy import batch must finish staging before declaration planning.');
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runReference) !== 1) {
            throw new RuntimeException('Declaration plan run reference must be 3-100 safe characters.');
        }
    }

    private function datasetKey(LegacyImportBatch $batch): string
    {
        $keys = collect(['applications', 'business_permit_applications'])->filter(fn (string $key): bool => $batch->records()->where('dataset_key', $key)->exists())->values();

        if ($keys->count() !== 1) {
            throw new RuntimeException('Legacy batch must contain exactly one declared application dataset.');
        }

        return $keys->sole();
    }

    public function snapshotHash(LegacyImportBatch $batch, string $dataset): string
    {
        $parts = [];
        foreach ($batch->records()->where('dataset_key', $dataset)->select(['id', 'payload_hash'])->orderBy('id')->cursor() as $record) {
            $parts[] = [$record->id, $record->payload_hash];
        }
        foreach (LegacyLineOfBusinessReconciliation::query()->where('legacy_source_id', $batch->legacy_source_id)->orderBy('id')->cursor() as $item) {
            $parts[] = [$item->id, $item->source_value_hash, $item->line_of_business_id, $item->status->value, $item->decision_authority, $item->evidence_reference, $item->updated_at?->toJSON()];
        }
        foreach (LineOfBusiness::query()->select(['id', 'code', 'name', 'is_active', 'updated_at'])->orderBy('id')->cursor() as $line) {
            $parts[] = ['target', ...$line->getAttributes()];
        }

        $applicationMappings = LegacyApplicationIdMapping::query()
            ->whereBelongsTo($batch, 'importBatch')
            ->orderBy('id')
            ->get();
        foreach ($applicationMappings as $mapping) {
            $parts[] = [
                'application_mapping',
                $mapping->id,
                hash('sha256', $mapping->legacy_id),
                $mapping->permit_application_id,
                $mapping->status,
                $mapping->updated_at?->toJSON(),
            ];
        }

        foreach (LegacyDeclarationLineMapping::query()->whereBelongsTo($batch, 'importBatch')->orderBy('id')->cursor() as $mapping) {
            $parts[] = [
                'declaration_mapping',
                $mapping->id,
                hash('sha256', $mapping->legacy_id),
                $mapping->line_index,
                $mapping->permit_application_line_id,
                $mapping->status,
                $mapping->updated_at?->toJSON(),
            ];
        }

        $applicationIds = $applicationMappings->pluck('permit_application_id')->unique()->values();
        foreach (PermitApplicationLine::query()->whereIn('permit_application_id', $applicationIds)->orderBy('id')->cursor() as $line) {
            $parts[] = ['application_line', ...$line->getAttributes()];
        }

        return $this->projector->hashCanonical($parts);
    }

    private function evidence(LegacyDeclarationMappingPlan $plan): LegacyDeclarationMappingPlan
    {
        return $plan->fresh(['importBatch.source', 'proposals']) ?? $plan;
    }
}
