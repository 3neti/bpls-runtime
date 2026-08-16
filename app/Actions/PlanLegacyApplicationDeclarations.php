<?php

namespace App\Actions;

use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyLineOfBusinessReconciliationStatus;
use App\Enums\LegacyMappingPlanStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\LegacyDeclarationMappingPlan;
use App\Models\LegacyImportBatch;
use App\Models\LegacyLineOfBusinessReconciliation;
use App\Models\LegacyRecord;
use App\Models\LineOfBusiness;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PlanLegacyApplicationDeclarations
{
    public const PlannerVersion = 'bpls.declaration-mapping-plan.v1';

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

            foreach (array_values($lines) as $index => $line) {
                $this->planLine($plan, $record, $index, is_array($line) ? $line : []);
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

    /** @param array<string, mixed> $line */
    private function planLine(LegacyDeclarationMappingPlan $plan, LegacyRecord $record, int $index, array $line): void
    {
        $category = $this->string($line['businessCategory'] ?? null);
        $categoryHash = $category === '' ? null : hash('sha256', $this->normalize($category));
        $reconciliation = $categoryHash === null ? null : LegacyLineOfBusinessReconciliation::query()
            ->where('legacy_source_id', $record->legacy_source_id)->where('source_dataset', 'groups')->where('source_value_hash', $categoryHash)->first();
        $target = $reconciliation?->line_of_business_id === null ? null : LineOfBusiness::query()->find($reconciliation->line_of_business_id);
        $reasons = [];
        $blocked = false;

        if ($categoryHash === null) {
            $reasons[] = 'business_category_missing';
            $blocked = true;
        } elseif (! $reconciliation instanceof LegacyLineOfBusinessReconciliation) {
            $reasons[] = 'accepted_line_of_business_reconciliation_missing';
            $blocked = true;
        } elseif ($reconciliation->status !== LegacyLineOfBusinessReconciliationStatus::Accepted
            || $reconciliation->decision_authority === null || $reconciliation->evidence_reference === null) {
            $reasons[] = 'line_of_business_reconciliation_not_accepted';
            $blocked = true;
        } elseif (! $target instanceof LineOfBusiness) {
            $reasons[] = 'reconciled_line_of_business_target_missing';
            $blocked = true;
        } elseif (! $target->is_active) {
            $reasons[] = 'reconciled_line_of_business_inactive';
        }

        $type = $this->string($line['permitApplicationType'] ?? $record->payload['permitApplicationType'] ?? null);
        $capital = $this->money($line['capitalInvestment'] ?? null);
        $gross = $this->grossMoney($line, $reasons);

        if ($type === 'New' && $capital === null) {
            $reasons[] = 'new_application_capital_not_exact_amount';
            $blocked = true;
        } elseif ($type === 'Renewal' && $gross === null) {
            $reasons[] = 'renewal_gross_sales_not_exact_amount';
            $blocked = true;
        } elseif (! in_array($type, ['New', 'Renewal'], true)) {
            $reasons[] = 'declaration_application_type_requires_reconciliation';
        }

        foreach (['feeOverrides', 'excludedFees', 'feeVariableMappings'] as $field) {
            if (is_array($line[$field] ?? null) && $line[$field] !== []) {
                $reasons[] = 'line_financial_configuration_migration_required';
                break;
            }
        }

        $status = $this->proposalStatus($blocked, $reasons);
        $projection = [
            'line_of_business_id' => $target?->id, 'declared_gross_sales_cents' => $gross ?? 0,
            'capital_investment_cents' => $capital ?? 0, 'quantity' => 1, 'started_on' => null,
            'metadata' => ['legacy_number_of_employees' => is_int($line['numberOfEmployees'] ?? null) ? $line['numberOfEmployees'] : null, 'legacy_category_hash' => $categoryHash],
        ];

        $plan->proposals()->updateOrCreate(
            ['legacy_record_id' => $record->id, 'line_index' => $index],
            [
                'legacy_line_of_business_reconciliation_id' => $reconciliation?->id, 'line_of_business_id' => $target?->id,
                'status' => $status, 'projection_hash' => $this->hash($projection), 'reasons' => array_values(array_unique($reasons)),
                'metadata' => ['legacy_application_id_sha256' => hash('sha256', $record->legacy_id), 'business_category_sha256' => $categoryHash,
                    'projected_capital_cents' => $capital, 'projected_gross_sales_cents' => $gross, 'financial_calculations' => false, 'domain_writes' => false],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  list<string>  $reasons
     */
    private function grossMoney(array $line, array &$reasons): ?int
    {
        $gross = $this->money($line['grossSales'] ?? null);
        $annual = $this->money($line['businessAnnualRevenue'] ?? null);

        if ($gross !== null && $annual !== null && $gross !== $annual) {
            $reasons[] = 'gross_sales_and_legacy_revenue_conflict';

            return null;
        }

        return $gross ?? $annual;
    }

    private function money(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $normalized = str_replace([',', ' '], '', (string) $value);

        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized) !== 1) {
            return null;
        }

        [$whole, $decimal] = array_pad(explode('.', $normalized, 2), 2, '');
        $decimal = str_pad($decimal, 2, '0');

        if (strlen($whole) > 16) {
            return null;
        }

        $cents = ((int) $whole * 100) + (int) $decimal;

        return $cents >= 0 ? $cents : null;
    }

    /** @param list<string> $reasons */
    private function proposalStatus(bool $blocked, array $reasons): LegacyMappingProposalStatus
    {
        if ($blocked) {
            return LegacyMappingProposalStatus::Blocked;
        }

        return $reasons === [] ? LegacyMappingProposalStatus::Ready : LegacyMappingProposalStatus::ReviewRequired;
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

    private function snapshotHash(LegacyImportBatch $batch, string $dataset): string
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

        return $this->hash($parts);
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->squish()->lower()->toString();
    }

    private function string(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }

    /** @param array<array-key, mixed> $value */
    private function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function evidence(LegacyDeclarationMappingPlan $plan): LegacyDeclarationMappingPlan
    {
        return $plan->fresh(['importBatch.source', 'proposals']) ?? $plan;
    }
}
