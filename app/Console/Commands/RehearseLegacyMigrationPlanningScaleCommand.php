<?php

namespace App\Console\Commands;

use App\Actions\GenerateLegacyMigrationScaleFixture;
use App\Actions\RehearseLegacyMigrationPlanningScale;
use App\Enums\LegacyImportBatchStatus;
use App\Enums\LegacyMappingPlanStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:rehearse-planning-scale
    {--profile=smoke : Profile key: smoke or ipil-observed-20260816}
    {--run-id= : Stable operator-provided rehearsal reference}
    {--rehearse : Confirm generation and planning of synthetic records}
    {--confirm-rehearse : Second explicit rehearsal confirmation}
    {--json : Write only structured output}')]
#[Description('Exercise legacy staging and planning at deterministic synthetic scale without writing BPLS domain records.')]
class RehearseLegacyMigrationPlanningScaleCommand extends Command
{
    private const ReportSchemaVersion = 'bpls.legacy-planning-scale-report.v1';

    public function handle(
        GenerateLegacyMigrationScaleFixture $generateFixture,
        RehearseLegacyMigrationPlanningScale $rehearse,
    ): int {
        try {
            if (! $this->option('rehearse') || ! $this->option('confirm-rehearse')) {
                throw new RuntimeException('Both --rehearse and --confirm-rehearse are required.');
            }
            $runReference = $this->option('run-id');
            if (! is_string($runReference) || $runReference === '') {
                throw new RuntimeException('A stable --run-id is required.');
            }
            $profileKey = $this->option('profile');
            if (! is_string($profileKey)) {
                throw new RuntimeException('A valid --profile is required.');
            }
            $fixture = $generateFixture->handle($runReference, $this->profile($profileKey));
            $reportPath = $fixture['artifact_root'].'/scale-rehearsal.json';
            $result = $rehearse->handle($fixture['manifest_path'], $runReference);
            if (Storage::disk('local')->exists($reportPath)) {
                $report = $this->existingReport($reportPath, $fixture['profile_hash']);
            } else {
                $report = $this->report($fixture, $result);
                $this->writeEvidence($fixture['artifact_root'], $report);
            }
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $summary = [
            'passed' => $report['result']['passed'],
            'run_id' => $report['run_id'],
            'profile' => $report['profile']['key'],
            'source_records' => $report['result']['source_records'],
            'staged_records' => $report['result']['staged_records'],
            'planning_proposals' => $report['result']['planning_proposals'],
            'domain_writes' => $report['safety']['domain_writes'],
            'production_data_used' => $report['safety']['production_data_used'],
            'production_parity_claimed' => $report['safety']['production_parity_claimed'],
            'cutover_authorized' => $report['safety']['cutover_authorized'],
            'artifacts' => Storage::disk('local')->path($fixture['artifact_root']),
        ];
        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Scale rehearsal: '.$report['run_id']);
            $this->line('Profile: '.$report['profile']['key']);
            $this->line("Records: {$summary['staged_records']} staged / {$summary['source_records']} generated");
            $this->line('Planning proposals: '.$summary['planning_proposals']);
            $this->line('Domain writes: none');
            $this->line('Production data used: no');
            $this->line('Production parity claimed: no');
            $this->line('Artifacts: '.$summary['artifacts']);
        }

        return $summary['passed'] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, mixed> */
    private function profile(string $key): array
    {
        return match ($key) {
            'smoke' => [
                'key' => 'smoke',
                'counts' => [
                    'business_owners' => 4,
                    'businesses' => 5,
                    'business_permit_applications' => 6,
                    'payment_schedules' => 6,
                    'payments' => 12,
                    'permit_clearances' => 6,
                    'permits' => 5,
                ],
                'lines_per_application' => 1,
                'evidence' => [
                    'exact_observations' => [],
                    'synthetic_assumptions' => ['small deterministic integration profile'],
                ],
            ],
            'ipil-observed-20260816' => [
                'key' => 'ipil-observed-20260816',
                'counts' => [
                    'business_owners' => 3_163,
                    'businesses' => 3_188,
                    'business_permit_applications' => 3_065,
                    'payment_schedules' => 3_065,
                    'payments' => 29_113,
                    'permit_clearances' => 3_065,
                    'permits' => 2_709,
                ],
                'lines_per_application' => 1,
                'evidence' => [
                    'exact_observations' => [
                        'business_owners' => 3_163,
                        'businesses' => 3_188,
                        'business_permit_applications' => 3_065,
                        'permits' => 2_709,
                        'unified_payment_transactions' => 29_113,
                        'completed_payment_transactions' => 28_643,
                        'clearance_types' => 5,
                    ],
                    'synthetic_assumptions' => [
                        'one payment schedule per application; production schedule count was not observed',
                        'payment records use the unified transaction total as a conservative planner load, not as a production payments-table count',
                        'one pending clearance per application; production assignment count was not observed',
                        'one declaration line per application; production nested-line count was not observed',
                    ],
                ],
            ],
            default => throw new RuntimeException("Unknown scale rehearsal profile [{$key}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function report(array $fixture, array $result): array
    {
        $plans = [
            'registry' => $this->planEvidence($result['registry_plan'], $result['registry_plan']->owner_proposal_count + $result['registry_plan']->business_proposal_count),
            'applications' => $this->planEvidence($result['application_plan'], $result['application_plan']->proposal_count),
            'declarations' => $this->planEvidence($result['declaration_plan'], $result['declaration_plan']->proposal_count),
            'financial' => $this->planEvidence($result['financial_plan'], $result['financial_plan']->proposal_count),
            'permit_evidence' => $this->planEvidence($result['permit_evidence_plan'], $result['permit_evidence_plan']->proposal_count),
        ];
        $planningCompleted = true;
        $proposalCount = 0;
        foreach ($plans as $plan) {
            $planningCompleted = $planningCompleted && in_array($plan['status'], [
                LegacyMappingPlanStatus::Planned->value,
                LegacyMappingPlanStatus::PlannedWithExceptions->value,
            ], true);
            $proposalCount += $plan['proposals'];
        }
        $batch = $result['batch'];
        $passed = $planningCompleted
            && in_array($batch->status, [LegacyImportBatchStatus::Staged, LegacyImportBatchStatus::StagedWithExceptions], true)
            && $batch->source_record_count === $batch->staged_record_count
            && $result['domain_writes'] === false;

        return [
            'schema_version' => self::ReportSchemaVersion,
            'rehearsal_version' => RehearseLegacyMigrationPlanningScale::RehearsalVersion,
            'run_id' => str($batch->run_reference)->beforeLast('-stage')->toString(),
            'profile_hash' => $fixture['profile_hash'],
            'profile' => $fixture['profile'],
            'batch_id' => $batch->id,
            'dataset_counts' => $fixture['dataset_counts'],
            'phases' => $result['phases'],
            'plans' => $plans,
            'readiness' => [
                'assessment_id' => $result['readiness']->id,
                'status' => $result['readiness']->status->value,
                'rehearsal_ready' => $result['readiness']->rehearsal_ready,
                'cutover_ready' => $result['readiness']->cutover_ready,
                'blocked_checks' => $this->blockedChecks($result['readiness']->checks),
            ],
            'result' => [
                'passed' => $passed,
                'source_records' => $batch->source_record_count,
                'staged_records' => $batch->staged_record_count,
                'staging_exceptions' => $batch->exception_count,
                'planning_proposals' => $proposalCount,
            ],
            'domain_counts_before' => $result['domain_counts_before'],
            'domain_counts_after' => $result['domain_counts_after'],
            'safety' => [
                'domain_writes' => $result['domain_writes'],
                'external_integrations' => false,
                'notifications' => false,
                'irreversible_actions' => false,
                'production_data_used' => false,
                'personal_data_recorded' => false,
                'production_export_claimed' => false,
                'production_parity_claimed' => false,
                'cutover_authorized' => false,
            ],
            'completed_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function planEvidence(Model $plan, int $proposalCount): array
    {
        $status = $plan->getAttribute('status');
        if (! $status instanceof LegacyMappingPlanStatus) {
            throw new RuntimeException('Scale rehearsal plan has an invalid status.');
        }

        return [
            'plan_id' => $plan->getKey(),
            'status' => $status->value,
            'proposals' => $proposalCount,
            'ready' => (int) $plan->getAttribute('ready_count'),
            'review_required' => (int) $plan->getAttribute('review_count'),
            'blocked' => (int) $plan->getAttribute('blocked_count'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $checks
     * @return list<string>
     */
    private function blockedChecks(?array $checks): array
    {
        $blocked = [];
        foreach ($checks ?? [] as $check) {
            if (($check['passed'] ?? false) === false && is_string($check['key'] ?? null)) {
                $blocked[] = $check['key'];
            }
        }

        return $blocked;
    }

    /** @return array<string, mixed> */
    private function existingReport(string $path, string $profileHash): array
    {
        $report = json_decode((string) Storage::disk('local')->get($path), true, flags: JSON_THROW_ON_ERROR);
        if (
            ! is_array($report)
            || ($report['schema_version'] ?? null) !== self::ReportSchemaVersion
            || ($report['rehearsal_version'] ?? null) !== RehearseLegacyMigrationPlanningScale::RehearsalVersion
            || ! is_string($report['profile_hash'] ?? null)
            || ! hash_equals($profileHash, $report['profile_hash'])
        ) {
            throw new RuntimeException('Existing scale rehearsal evidence does not match the requested profile.');
        }

        return $report;
    }

    /** @param array<string, mixed> $report */
    private function writeEvidence(string $root, array $report): void
    {
        $reportWritten = Storage::disk('local')->put($root.'/scale-rehearsal.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        $reviewWritten = Storage::disk('local')->put($root.'/review.md', "# Legacy Planning Scale Rehearsal Review\n\nReviewer status: Pending\nReviewer:\nReviewed at:\nNotes:\n");
        if (! $reportWritten || ! $reviewWritten) {
            throw new RuntimeException('Legacy planning scale rehearsal evidence could not be written to private storage.');
        }
    }

    private function failCommand(string $message): int
    {
        if ($this->option('json')) {
            $this->line(json_encode(['passed' => false, 'error' => $message], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }
}
