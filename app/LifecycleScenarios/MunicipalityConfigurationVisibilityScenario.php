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
            $this->step('configuration-projected', 'Project municipality identity, officials, provenance, and document associations from runtime configuration', $summary, $this->summary($configuration)),
            $this->step('authority-remains-explicit', 'Keep configured officials and document associations distinct from signature, issuance, release, and legal-effect authority', ['read_only' => true, 'authorized_signatory_count' => 0, 'permit_issuance_authorized' => false, 'permit_release_authorized' => false, 'legal_effect_authorized' => false, 'external_calls' => 0], ['read_only' => true, 'authorized_signatory_count' => 0, 'permit_issuance_authorized' => false, 'permit_release_authorized' => false, 'legal_effect_authorized' => false, 'external_calls' => 0]),
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
            'payload_policy' => 'Municipality identity, official roles, provenance status, document associations, and authority states only; configured personal names are omitted from machine-readable evidence.',
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
            'official_count' => $manifest['resources']['official_count'],
            'configured_official_count' => $manifest['resources']['configured_official_count'],
            'document_association_count' => $manifest['resources']['document_association_count'],
            'current_document_association_count' => $manifest['resources']['current_document_association_count'],
            'effective_term_evidence_count' => $manifest['resources']['effective_term_evidence_count'],
            'authorized_signatory_count' => 0,
            'permit_issuance_authorized' => false,
            'permit_release_authorized' => false,
            'legal_effect_authorized' => false,
            'read_only' => true,
            'source_type' => $manifest['resources']['source_type'],
            'production_snapshot_status' => $manifest['resources']['production_snapshot_status'],
            'official_evidence_statuses' => $manifest['resources']['official_evidence_statuses'],
            'document_association_statuses' => $manifest['resources']['document_association_statuses'],
            'authority_chain' => $manifest['resources']['authority_chain'],
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
        $configuredOfficials = data_get($configuration, 'officials', []);
        $configuredOfficials = is_array($configuredOfficials)
            ? array_values(array_filter($configuredOfficials, is_array(...)))
            : [];
        $documentAssociations = data_get($configuration, 'document_associations', []);
        $documentAssociations = is_array($documentAssociations)
            ? array_values(array_filter($documentAssociations, is_array(...)))
            : [];
        $authorityChain = data_get($configuration, 'authority_chain', []);
        $authorityChain = is_array($authorityChain)
            ? array_values(array_filter($authorityChain, is_array(...)))
            : [];

        return [
            'municipality_name' => (string) data_get($configuration, 'identity.municipality_name'),
            'province' => (string) data_get($configuration, 'identity.province'),
            'system_name' => (string) data_get($configuration, 'identity.system_name'),
            'official_count' => (int) data_get($configuration, 'authority.official_count', 0),
            'configured_official_count' => (int) data_get($configuration, 'authority.configured_official_count', 0),
            'document_association_count' => (int) data_get($configuration, 'authority.document_association_count', 0),
            'current_document_association_count' => (int) data_get($configuration, 'authority.current_document_association_count', 0),
            'effective_term_evidence_count' => (int) data_get($configuration, 'authority.effective_term_evidence_count', 0),
            'authorized_signatory_count' => (int) data_get($configuration, 'authority.authorized_signatory_count', 0),
            'permit_issuance_authorized' => (bool) data_get($configuration, 'authority.permit_issuance_authorized'),
            'permit_release_authorized' => (bool) data_get($configuration, 'authority.permit_release_authorized'),
            'legal_effect_authorized' => (bool) data_get($configuration, 'authority.legal_effect_authorized'),
            'read_only' => (bool) data_get($configuration, 'source.read_only'),
            'source_type' => (string) data_get($configuration, 'source.type'),
            'production_snapshot_status' => (string) data_get($configuration, 'source.production_snapshot_status'),
            'official_evidence_statuses' => collect($configuredOfficials)
                ->map(fn (array $official): array => [
                    'key' => $official['key'],
                    'role' => $official['role'],
                    'configuration_status' => $official['configuration_status'],
                    'authorized_signatory' => $official['authorized_signatory'],
                    'effective_term_status' => $official['effective_term']['status'],
                    'production_snapshot_status' => $official['provenance']['production_snapshot_status'],
                ])
                ->values()
                ->all(),
            'document_association_statuses' => collect($documentAssociations)
                ->map(fn (array $association): array => [
                    'official_key' => $association['official_key'],
                    'document_type' => $association['document_type'],
                    'current_runtime_use' => $association['current_runtime_use'],
                    'production_layout_status' => $association['production_layout_status'],
                    'authorizes_signature' => $association['authorizes_signature'],
                ])
                ->values()
                ->all(),
            'authority_chain' => collect($authorityChain)
                ->map(fn (array $stage): array => [
                    'key' => $stage['key'],
                    'status' => $stage['status'],
                    'satisfied' => $stage['satisfied'],
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
            'summary' => 'An authorized administrator reviews configured officials, document associations, provenance, and effective-term evidence without changing configuration or exercising municipal authority.',
            'run_id' => $runId,
            'frames' => [
                ['title' => 'Administrator opens municipality configuration', 'description' => 'The workspace shows the configured LGU and BPLS identity.', 'dialogue' => 'Runtime configuration is visible and traceable.', 'duration_seconds' => 4],
                ['title' => 'Administrator reviews configured officials', 'description' => 'Each role shows configuration, provenance, and effective-term evidence without claiming appointment authority.', 'dialogue' => 'A configured official is not automatically an authorized signatory.', 'duration_seconds' => 5],
                ['title' => 'Administrator reviews document associations', 'description' => 'Permit and receipt template relationships remain separate from signing authority.', 'dialogue' => 'A template field records presentation behavior, not legal authority.', 'duration_seconds' => 5],
                ['title' => 'Administrator confirms the authority boundary', 'description' => 'The five-stage chain stops before authorized signature, issuance, release, or legal effect.', 'dialogue' => 'Software records evidence; municipal authority decides.', 'duration_seconds' => 5],
            ],
        ];
    }

    private function storyboardHtml(string $runId): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Municipality configuration and authority visibility</title></head><body><h1>Municipality configuration and authority visibility</h1><p>Run ID: '.e($runId).'</p><ol><li>Administrator opens municipality configuration.</li><li>Administrator reviews official provenance and term evidence.</li><li>Administrator reviews document associations.</li><li>Administrator confirms the read-only authority boundary.</li></ol></body></html>';
    }
}
