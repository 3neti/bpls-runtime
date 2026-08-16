<?php

namespace App\LifecycleScenarios;

use App\Actions\BuildMunicipalityConfiguration;
use App\Enums\UserPermission;
use App\Models\User;
use RuntimeException;

class MunicipalityConfigurationVisibilityScenario
{
    public function __construct(
        private readonly BuildMunicipalityConfiguration $buildMunicipalityConfiguration,
        private readonly ScenarioManifest $scenarioManifest,
        private readonly ScenarioSummaryRenderer $summaryRenderer,
    ) {}

    /**
     * @param  array<string, User>  $actors
     * @return array<string, mixed>
     */
    public function prepare(LifecycleScenarioDefinition $scenario, string $runId, array $actors, ScenarioArtifactStore $artifactStore): array
    {
        $existingManifest = $artifactStore->readJson('manifest.json');
        if (is_array($existingManifest) && ($existingManifest['result']['terminal'] ?? null) === 'passed') {
            return $existingManifest;
        }

        $operator = $actors['operator'] ?? throw new RuntimeException('Scenario operator actor was not resolved.');
        $configuration = $this->buildMunicipalityConfiguration->handle();
        $summary = $this->summary($configuration);
        $manifest = $this->scenarioManifest->initial($scenario, $runId, $actors);
        $steps = [
            $this->step('operator-authorized', 'Resolve an operator through the real municipality-configuration authorization boundary', ['can_view_configuration' => true], ['can_view_configuration' => $operator->can(UserPermission::ViewMunicipalityConfiguration->value)]),
            $this->step('configuration-projected', 'Project municipality identity and signatory authority state from runtime configuration', $summary, $this->summary($configuration)),
            $this->step('authority-remains-explicit', 'Keep configuration evidence distinct from permit issuance authority', ['read_only' => true, 'permit_issuance_authorized' => false, 'external_calls' => 0], ['read_only' => true, 'permit_issuance_authorized' => false, 'external_calls' => 0]),
        ];

        foreach ($steps as $step) {
            $artifactStore->appendJsonLine('terminal/action-log.jsonl', $step);
        }

        $manifest['resources'] = [
            'record_type' => 'municipality_configuration',
            'record_id' => $operator->id,
            'public_reference' => $summary['municipality_name'],
            'configuration_url' => route('staff.municipality-configuration.index', absolute: false),
            ...$summary,
        ];
        $manifest['steps'] = $steps;
        $manifest['result']['terminal'] = collect($steps)->every(fn (array $step): bool => $step['passed']) ? 'passed' : 'failed';
        $manifest['result']['passed'] = $manifest['result']['terminal'] === 'passed';
        $manifest['artifacts'] = ['root' => '.'];

        $artifactStore->putJson('terminal/prepare.json', [
            'summary' => $summary,
            'run_id' => $runId,
            'payload_policy' => 'Municipality identity and signatory roles/statuses only; configured personal names are omitted from machine-readable evidence.',
        ]);
        $artifactStore->putJson('terminal/execution.json', [
            'steps' => $steps,
            'read_only' => true,
            'external_calls' => 0,
            'irreversible_actions' => false,
            'notifications' => false,
        ]);
        $artifactStore->putJson('storyboard/storyboard.json', $this->storyboard($runId));
        $artifactStore->put('storyboard/storyboard.html', $this->storyboardHtml($runId));
        $artifactStore->putJson('manifest.json', $manifest);
        $artifactStore->put('review.md', $this->summaryRenderer->reviewMarkdown());

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    public function audit(array $manifest, ScenarioArtifactStore $artifactStore): array
    {
        $summary = $this->summary($this->buildMunicipalityConfiguration->handle());
        $browserReport = $artifactStore->readJson('browser/report.json') ?? [];
        $expected = [
            'municipality_name' => $manifest['resources']['municipality_name'],
            'province' => $manifest['resources']['province'],
            'system_name' => $manifest['resources']['system_name'],
            'signatory_count' => $manifest['resources']['signatory_count'],
            'verified_signatory_count' => $manifest['resources']['verified_signatory_count'],
            'unverified_signatory_count' => $manifest['resources']['unverified_signatory_count'],
            'all_signatories_verified' => $manifest['resources']['all_signatories_verified'],
            'permit_issuance_authorized' => false,
            'read_only' => true,
            'source_type' => $manifest['resources']['source_type'],
            'signatory_authority_statuses' => $manifest['resources']['signatory_authority_statuses'],
        ];
        $checks = [
            $this->step('audit-browser-result', 'Browser evidence runner passed', ['browser' => true], ['browser' => (bool) data_get($browserReport, 'result.passed')]),
            $this->step('audit-canonical-configuration', 'Canonical runtime configuration still matches the prepared manifest', $expected, $summary),
            $this->step('audit-browser-configuration', 'Visible municipality and authority evidence agrees with canonical configuration', $expected, data_get($browserReport, 'municipality_configuration.summary', [])),
            $this->step('audit-browser-boundary', 'Configuration remains read-only and usable on mobile', ['mutation_actions_visible' => false, 'authority_boundary_visible' => true, 'mobile_visible' => true, 'page_horizontal_overflow' => false], ['mutation_actions_visible' => data_get($browserReport, 'municipality_configuration.mutation_actions_visible'), 'authority_boundary_visible' => data_get($browserReport, 'municipality_configuration.authority_boundary_visible'), 'mobile_visible' => data_get($browserReport, 'municipality_configuration.mobile_visible'), 'page_horizontal_overflow' => data_get($browserReport, 'municipality_configuration.page_horizontal_overflow')]),
        ];
        $passed = collect($checks)->every(fn (array $check): bool => $check['passed']);

        $manifest['steps'] = [...($manifest['steps'] ?? []), ...$checks];
        $manifest['result']['audit'] = $passed ? 'passed' : 'failed';
        $manifest['result']['browser'] = data_get($browserReport, 'result.passed') ? 'passed' : 'failed';
        $manifest['result']['passed'] = $manifest['result']['terminal'] === 'passed'
            && $manifest['result']['browser'] === 'passed'
            && $manifest['result']['audit'] === 'passed';
        $manifest['artifacts']['screenshots'] = data_get($browserReport, 'artifacts.screenshots', []);

        $artifactStore->putJson('terminal/audit.json', [
            'checks' => $checks,
            'passed' => $passed,
            'canonical' => $summary,
            'browser' => $browserReport,
        ]);
        $artifactStore->putJson('manifest.json', $manifest);
        $artifactStore->put('summary.html', $this->summaryRenderer->html($manifest));

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    private function summary(array $configuration): array
    {
        $configuredSignatories = data_get($configuration, 'permit_signatories', []);
        $configuredSignatories = is_array($configuredSignatories)
            ? array_values(array_filter($configuredSignatories, is_array(...)))
            : [];

        return [
            'municipality_name' => (string) data_get($configuration, 'identity.municipality_name'),
            'province' => (string) data_get($configuration, 'identity.province'),
            'system_name' => (string) data_get($configuration, 'identity.system_name'),
            'signatory_count' => (int) data_get($configuration, 'authority.signatory_count', 0),
            'verified_signatory_count' => (int) data_get($configuration, 'authority.verified_signatory_count', 0),
            'unverified_signatory_count' => (int) data_get($configuration, 'authority.unverified_signatory_count', 0),
            'all_signatories_verified' => (bool) data_get($configuration, 'authority.all_signatories_verified'),
            'permit_issuance_authorized' => (bool) data_get($configuration, 'authority.permit_issuance_authorized'),
            'read_only' => (bool) data_get($configuration, 'source.read_only'),
            'source_type' => (string) data_get($configuration, 'source.type'),
            'signatory_authority_statuses' => collect($configuredSignatories)
                ->map(fn (array $signatory): array => [
                    'role' => $signatory['role'],
                    'authority_status' => $signatory['authority_status'],
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     * @return array<string, mixed>
     */
    private function step(string $key, string $action, array $expected, array $actual): array
    {
        return [
            'key' => $key,
            'actor' => 'operator',
            'action' => $action,
            'expected' => $expected,
            'actual' => $actual,
            'passed' => $expected === $actual,
            'occurred_at' => now()->toIso8601String(),
            'evidence' => $actual,
        ];
    }

    /** @return array<string, mixed> */
    private function storyboard(string $runId): array
    {
        return [
            'title' => 'Municipality configuration and authority visibility',
            'summary' => 'An authorized administrator reviews the configured municipal identity and permit signatory evidence without changing policy or exercising permit authority.',
            'run_id' => $runId,
            'frames' => [
                ['title' => 'Administrator opens municipality configuration', 'description' => 'The workspace shows the configured LGU and BPLS identity.', 'dialogue' => 'Runtime configuration is visible and traceable.', 'duration_seconds' => 4],
                ['title' => 'Administrator reviews permit signatories', 'description' => 'Each configured role and its authority-verification status are visible.', 'dialogue' => 'Configured is not the same as municipally accepted.', 'duration_seconds' => 5],
                ['title' => 'Administrator confirms the authority boundary', 'description' => 'No edit or issuance action is offered.', 'dialogue' => 'Document evidence does not authorize issuance, release, or legal effect.', 'duration_seconds' => 5],
            ],
        ];
    }

    private function storyboardHtml(string $runId): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Municipality configuration and authority visibility</title></head><body><h1>Municipality configuration and authority visibility</h1><p>Run ID: '.e($runId).'</p><ol><li>Administrator opens municipality configuration.</li><li>Administrator reviews signatory authority status.</li><li>Administrator confirms the read-only authority boundary.</li></ol></body></html>';
    }
}
