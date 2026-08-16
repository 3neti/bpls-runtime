<?php

namespace App\Actions;

use App\Models\Assessment;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LegacyApplicationMappingPlan;
use App\Models\LegacyDeclarationMappingPlan;
use App\Models\LegacyFinancialMappingPlan;
use App\Models\LegacyImportBatch;
use App\Models\LegacyMappingPlan;
use App\Models\LegacyMigrationReadinessAssessment;
use App\Models\LegacyPermitEvidencePlan;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use App\Models\PermitApplicationLine;
use App\Models\PermitClearance;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Closure;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class RehearseLegacyMigrationPlanningScale
{
    public const RehearsalVersion = 'bpls.legacy-planning-scale-rehearsal.v1';

    public function __construct(
        private StageLegacyExport $stageLegacyExport,
        private PlanLegacyRegistryMigration $planRegistry,
        private PlanLegacyPermitApplications $planApplications,
        private PlanLegacyApplicationDeclarations $planDeclarations,
        private PlanLegacyFinancialDependencies $planFinancial,
        private PlanLegacyPermitEvidence $planPermitEvidence,
        private AssessLegacyMigrationReadiness $assessReadiness,
    ) {}

    /**
     * @return array{
     *   batch: LegacyImportBatch,
     *   registry_plan: LegacyMappingPlan,
     *   application_plan: LegacyApplicationMappingPlan,
     *   declaration_plan: LegacyDeclarationMappingPlan,
     *   financial_plan: LegacyFinancialMappingPlan,
     *   permit_evidence_plan: LegacyPermitEvidencePlan,
     *   readiness: LegacyMigrationReadinessAssessment,
     *   phases: list<array<string, mixed>>,
     *   domain_counts_before: array<string, int>,
     *   domain_counts_after: array<string, int>,
     *   domain_writes: bool
     * }
     */
    public function handle(string $manifestPath, string $runReference): array
    {
        $this->assertEnvironment();
        $this->assertRunReference($runReference);
        $domainBefore = $this->domainCounts();
        $phases = [];

        [$batch, $phases[]] = $this->phase('staging', fn (): LegacyImportBatch => $this->stageLegacyExport->handle($manifestPath, $runReference.'-stage'));
        [$registryPlan, $phases[]] = $this->phase('registry_planning', fn (): LegacyMappingPlan => $this->planRegistry->handle($batch, $runReference.'-registry'));
        [$declarationPlan, $phases[]] = $this->phase('declaration_planning', fn (): LegacyDeclarationMappingPlan => $this->planDeclarations->handle($batch, $runReference.'-declarations'));
        [$applicationPlan, $phases[]] = $this->phase('application_planning', fn (): LegacyApplicationMappingPlan => $this->planApplications->handle($batch, $runReference.'-applications'));
        [$financialPlan, $phases[]] = $this->phase('financial_planning', fn (): LegacyFinancialMappingPlan => $this->planFinancial->handle($batch, $runReference.'-financial'));
        [$permitEvidencePlan, $phases[]] = $this->phase('permit_evidence_planning', fn (): LegacyPermitEvidencePlan => $this->planPermitEvidence->handle($batch, $runReference.'-permit-evidence'));
        [$readiness, $phases[]] = $this->phase('readiness_assessment', fn (): LegacyMigrationReadinessAssessment => $this->assessReadiness->handle($batch, $runReference.'-readiness'));

        $phases[0] = $this->withThroughput($phases[0], $batch->staged_record_count);
        $phases[1] = $this->withThroughput($phases[1], $registryPlan->owner_proposal_count + $registryPlan->business_proposal_count);
        $phases[2] = $this->withThroughput($phases[2], $declarationPlan->proposal_count);
        $phases[3] = $this->withThroughput($phases[3], $applicationPlan->proposal_count);
        $phases[4] = $this->withThroughput($phases[4], $financialPlan->proposal_count);
        $phases[5] = $this->withThroughput($phases[5], $permitEvidencePlan->proposal_count);
        $phases[6] = $this->withThroughput($phases[6], $readiness->check_count);
        $domainAfter = $this->domainCounts();

        return [
            'batch' => $batch,
            'registry_plan' => $registryPlan,
            'application_plan' => $applicationPlan,
            'declaration_plan' => $declarationPlan,
            'financial_plan' => $financialPlan,
            'permit_evidence_plan' => $permitEvidencePlan,
            'readiness' => $readiness,
            'phases' => $phases,
            'domain_counts_before' => $domainBefore,
            'domain_counts_after' => $domainAfter,
            'domain_writes' => $domainBefore !== $domainAfter,
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Closure(): TModel  $callback
     * @return array{0: TModel, 1: array<string, mixed>}
     */
    private function phase(string $key, Closure $callback): array
    {
        $started = hrtime(true);
        $memoryBefore = memory_get_usage(true);
        $peakBefore = memory_get_peak_usage(true);
        $result = $callback();
        $durationMilliseconds = (hrtime(true) - $started) / 1_000_000;

        return [$result, [
            'key' => $key,
            'duration_ms' => round($durationMilliseconds, 3),
            'memory_before_bytes' => $memoryBefore,
            'memory_after_bytes' => memory_get_usage(true),
            'peak_memory_delta_bytes' => max(0, memory_get_peak_usage(true) - $peakBefore),
            'records_processed' => 0,
            'records_per_second' => 0.0,
            'result_id' => $result->getKey(),
        ]];
    }

    /** @param array<string, mixed> $phase
     * @return array<string, mixed>
     */
    private function withThroughput(array $phase, int $records): array
    {
        $duration = (float) $phase['duration_ms'];

        return [
            ...$phase,
            'records_processed' => $records,
            'records_per_second' => $duration > 0 ? round($records / ($duration / 1000), 2) : 0.0,
        ];
    }

    /** @return array<string, int> */
    private function domainCounts(): array
    {
        return [
            'business_owners' => BusinessOwner::query()->count(),
            'businesses' => Business::query()->count(),
            'permit_applications' => PermitApplication::query()->count(),
            'permit_application_lines' => PermitApplicationLine::query()->count(),
            'assessments' => Assessment::query()->count(),
            'payment_schedules' => PaymentSchedule::query()->count(),
            'treasury_collections' => TreasuryCollection::query()->count(),
            'receipts' => Receipt::query()->count(),
            'permit_clearances' => PermitClearance::query()->count(),
            'permit_application_documents' => PermitApplicationDocument::query()->count(),
        ];
    }

    private function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy migration planning scale rehearsals are restricted to local and testing environments.');
        }
    }

    private function assertRunReference(string $runReference): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,49}$/', $runReference) !== 1) {
            throw new RuntimeException('Planning scale rehearsal run reference must contain 3-50 safe characters.');
        }
    }
}
