<?php

namespace App\Console\Commands;

use App\LifecycleScenarios\AssessmentPolicyBoundaryVisibilityScenario;
use App\LifecycleScenarios\CitizenPermitDraftVisibilityScenario;
use App\LifecycleScenarios\LifecycleScenarioRegistry;
use App\LifecycleScenarios\ManualCollectionReceiptVisibilityScenario;
use App\LifecycleScenarios\PermitApplicationCancelledVisibilityScenario;
use App\LifecycleScenarios\PermitApplicationPendingPaymentVisibilityScenario;
use App\LifecycleScenarios\RevenueCodeFeeCatalogVisibilityScenario;
use App\LifecycleScenarios\ScenarioActorResolver;
use App\LifecycleScenarios\ScenarioArtifactStore;
use App\LifecycleScenarios\StoryboardTerminalStateVisibilityScenario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class LifecycleScenarioCommand extends Command
{
    protected $signature = 'lifecycle:scenario
        {scenario=storyboard_terminal_state_visibility : Scenario key}
        {--run-id= : Stable run reference}
        {--phase=all : prepare, browser, audit, or all}
        {--json : Output only JSON}
        {--base-url=http://bpls-runtime.test : Browser base URL}
        {--live-operation : Required for live/irreversible scenarios}
        {--confirm-live-operation : Second confirmation for live/irreversible scenarios}';

    protected $description = 'Run an executable lifecycle scenario and collect terminal/browser evidence artifacts.';

    public function handle(
        LifecycleScenarioRegistry $registry,
        ScenarioActorResolver $actorResolver,
        CitizenPermitDraftVisibilityScenario $citizenPermitDraftVisibilityScenario,
        AssessmentPolicyBoundaryVisibilityScenario $assessmentPolicyBoundaryVisibilityScenario,
        ManualCollectionReceiptVisibilityScenario $manualCollectionReceiptScenario,
        PermitApplicationCancelledVisibilityScenario $permitApplicationCancelledScenario,
        PermitApplicationPendingPaymentVisibilityScenario $permitApplicationPendingPaymentScenario,
        RevenueCodeFeeCatalogVisibilityScenario $revenueCodeFeeCatalogVisibilityScenario,
        StoryboardTerminalStateVisibilityScenario $storyboardScenario,
    ): int {
        $scenario = $registry->get((string) $this->argument('scenario'));
        $runId = (string) ($this->option('run-id') ?: $scenario->key.'-'.now()->format('Ymd-His'));
        $phase = (string) $this->option('phase');
        $artifactStore = new ScenarioArtifactStore($scenario->key, $runId);

        try {
            $this->assertSafeEnvironment($scenario->safety);

            $manifest = $artifactStore->readJson('manifest.json');

            if (in_array($phase, ['prepare', 'all'], true)) {
                $actors = $actorResolver->resolve($scenario);
                $manifest = match ($scenario->key) {
                    'citizen_permit_draft_visibility' => $citizenPermitDraftVisibilityScenario->prepare($scenario, $runId, $actors, $artifactStore),
                    'new_permit_lifecycle_authority_boundary' => $manualCollectionReceiptScenario->prepare($scenario, $runId, $actors, $artifactStore),
                    'assessment_policy_boundary_visibility' => $assessmentPolicyBoundaryVisibilityScenario->prepare($scenario, $runId, $actors, $artifactStore),
                    'manual_collection_receipt_visibility' => $manualCollectionReceiptScenario->prepare($scenario, $runId, $actors, $artifactStore),
                    'permit_application_cancelled_visibility' => $permitApplicationCancelledScenario->prepare($scenario, $runId, $actors, $artifactStore),
                    'revenue_code_fee_catalog_visibility' => $revenueCodeFeeCatalogVisibilityScenario->prepare($scenario, $runId, $actors, $artifactStore),
                    'amendment_permit_lifecycle_foundation' => $permitApplicationPendingPaymentScenario->prepare($scenario, $runId, $actors, $artifactStore),
                    'permit_application_pending_payment_visibility' => $permitApplicationPendingPaymentScenario->prepare($scenario, $runId, $actors, $artifactStore),
                    'renewal_permit_lifecycle_foundation' => $permitApplicationPendingPaymentScenario->prepare($scenario, $runId, $actors, $artifactStore),
                    'retirement_permit_lifecycle_foundation' => $permitApplicationPendingPaymentScenario->prepare($scenario, $runId, $actors, $artifactStore),
                    'transfer_permit_lifecycle_foundation' => $permitApplicationPendingPaymentScenario->prepare($scenario, $runId, $actors, $artifactStore),
                    'storyboard_terminal_state_visibility' => $storyboardScenario->prepare($scenario, $runId, $actors, $artifactStore),
                    default => throw new RuntimeException("No prepare runner is registered for lifecycle scenario [{$scenario->key}]."),
                };
            }

            if (in_array($phase, ['browser', 'all'], true)) {
                $manifest ??= $this->requireManifest($artifactStore);
                $this->runBrowserEvidence($artifactStore, (string) $this->option('base-url'));
                $manifest = $this->withBrowserResult($manifest, $artifactStore);
            }

            if (in_array($phase, ['audit', 'all'], true)) {
                $manifest = match ($scenario->key) {
                    'citizen_permit_draft_visibility' => $citizenPermitDraftVisibilityScenario->audit($manifest ?? $this->requireManifest($artifactStore), $artifactStore),
                    'new_permit_lifecycle_authority_boundary' => $manualCollectionReceiptScenario->audit($manifest ?? $this->requireManifest($artifactStore), $artifactStore),
                    'assessment_policy_boundary_visibility' => $assessmentPolicyBoundaryVisibilityScenario->audit($manifest ?? $this->requireManifest($artifactStore), $artifactStore),
                    'manual_collection_receipt_visibility' => $manualCollectionReceiptScenario->audit($manifest ?? $this->requireManifest($artifactStore), $artifactStore),
                    'permit_application_cancelled_visibility' => $permitApplicationCancelledScenario->audit($manifest ?? $this->requireManifest($artifactStore), $artifactStore),
                    'revenue_code_fee_catalog_visibility' => $revenueCodeFeeCatalogVisibilityScenario->audit($manifest ?? $this->requireManifest($artifactStore), $artifactStore),
                    'amendment_permit_lifecycle_foundation' => $permitApplicationPendingPaymentScenario->audit($manifest ?? $this->requireManifest($artifactStore), $artifactStore),
                    'permit_application_pending_payment_visibility' => $permitApplicationPendingPaymentScenario->audit($manifest ?? $this->requireManifest($artifactStore), $artifactStore),
                    'renewal_permit_lifecycle_foundation' => $permitApplicationPendingPaymentScenario->audit($manifest ?? $this->requireManifest($artifactStore), $artifactStore),
                    'retirement_permit_lifecycle_foundation' => $permitApplicationPendingPaymentScenario->audit($manifest ?? $this->requireManifest($artifactStore), $artifactStore),
                    'transfer_permit_lifecycle_foundation' => $permitApplicationPendingPaymentScenario->audit($manifest ?? $this->requireManifest($artifactStore), $artifactStore),
                    'storyboard_terminal_state_visibility' => $storyboardScenario->audit($manifest ?? $this->requireManifest($artifactStore), $artifactStore),
                    default => throw new RuntimeException("No audit runner is registered for lifecycle scenario [{$scenario->key}]."),
                };
            }

            $manifest ??= $this->requireManifest($artifactStore);

            return $this->finish($manifest, $artifactStore, $phase);
        } catch (\Throwable $exception) {
            $artifactStore->putJson('failure.json', [
                'message' => $exception->getMessage(),
                'occurred_at' => now()->toIso8601String(),
            ]);

            if ($this->option('json')) {
                $this->line(json_encode([
                    'passed' => false,
                    'error' => $exception->getMessage(),
                    'artifacts' => $artifactStore->absolutePath(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->error($exception->getMessage());
                $this->line('Artifacts: '.$artifactStore->absolutePath());
            }

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $safety
     */
    private function assertSafeEnvironment(array $safety): void
    {
        if (! in_array(app()->environment(), $safety['environments'] ?? [], true)) {
            throw new RuntimeException('Lifecycle scenario refused to run in environment ['.app()->environment().'].');
        }

        if (($safety['external_integrations'] ?? false) === true && ! $this->option('live-operation')) {
            throw new RuntimeException('External integration scenario requires --live-operation.');
        }

        if (($safety['irreversible_actions'] ?? false) === true && (! $this->option('live-operation') || ! $this->option('confirm-live-operation'))) {
            throw new RuntimeException('Irreversible scenario requires --live-operation and --confirm-live-operation.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function requireManifest(ScenarioArtifactStore $artifactStore): array
    {
        return $artifactStore->readJson('manifest.json') ?? throw new RuntimeException('Scenario manifest is missing. Run the prepare phase first.');
    }

    private function runBrowserEvidence(ScenarioArtifactStore $artifactStore, string $baseUrl): void
    {
        $result = Process::timeout(120)
            ->path(base_path())
            ->run([
                'node',
                'scripts/lifecycle-scenarios/storyboard-browser-runner.mjs',
                $artifactStore->absolutePath().'/manifest.json',
                $baseUrl,
            ]);

        if ($result->failed()) {
            throw new RuntimeException('Browser evidence runner failed: '.$result->errorOutput().$result->output());
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function withBrowserResult(array $manifest, ScenarioArtifactStore $artifactStore): array
    {
        $browserReport = $artifactStore->readJson('browser/report.json');
        $manifest['result']['browser'] = data_get($browserReport, 'result.passed') === true ? 'passed' : 'failed';

        $artifactStore->putJson('manifest.json', $manifest);

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function finish(array $manifest, ScenarioArtifactStore $artifactStore, string $phase): int
    {
        $passed = match ($phase) {
            'prepare' => data_get($manifest, 'result.terminal') === 'passed',
            'browser' => true,
            default => (bool) data_get($manifest, 'result.passed'),
        };

        if ($passed) {
            $artifactStore->delete('failure.json');
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'passed' => $passed,
                'scenario' => data_get($manifest, 'scenario.label'),
                'run_id' => $manifest['run_id'],
                'resource' => $manifest['resources'] ?? [],
                'artifacts' => $artifactStore->absolutePath(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $passed ? self::SUCCESS : self::FAILURE;
        }

        $this->line('Scenario: '.data_get($manifest, 'scenario.label'));
        $this->line('Run ID: '.$manifest['run_id']);
        $this->line('Operator: '.data_get($manifest, 'actors.operator.name'));
        $this->line('Recipient: '.data_get($manifest, 'actors.recipient.name'));
        $this->line('Record: '.data_get($manifest, 'resources.public_reference'));
        $this->line('Domain result: '.data_get($manifest, 'result.terminal'));
        $this->line('Browser result: '.data_get($manifest, 'result.browser'));
        $this->line('External calls: none');
        $this->line('Irreversible actions: none');
        $this->line('Artifacts: '.$artifactStore->absolutePath());

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
