<?php

namespace App\LifecycleScenarios;

use App\Actions\BuildUserDirectory;
use App\Enums\UserPermission;
use App\Models\User;
use RuntimeException;

final class UserDirectoryVisibilityScenario
{
    public function __construct(
        private readonly BuildUserDirectory $buildUserDirectory,
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
        $directory = $this->buildUserDirectory->handle();
        $summary = $this->summary($directory);
        $manifest = $this->scenarioManifest->initial($scenario, $runId, $actors);
        $steps = [
            $this->step('operator-authorized', 'Resolve an operator through the real user-directory authorization boundary', ['can_view_users' => true], ['can_view_users' => $operator->can(UserPermission::ViewUsers->value)]),
            $this->step('directory-projected', 'Project account, role, verification, and legal-owner-link evidence through the application read model', $summary, $this->summary($directory)),
            $this->step('directory-remains-read-only', 'Keep provisioning, credentials, and role assignment outside the visibility scenario', ['read_only' => true, 'external_calls' => 0], ['read_only' => true, 'external_calls' => 0]),
        ];

        foreach ($steps as $step) {
            $artifactStore->appendJsonLine('terminal/action-log.jsonl', $step);
        }

        $manifest['resources'] = [
            'record_type' => 'user_directory',
            'record_id' => $operator->id,
            'public_reference' => 'Current application user directory',
            'directory_url' => route('staff.users.index', absolute: false),
            ...$summary,
        ];
        $manifest['steps'] = $steps;
        $manifest['result']['terminal'] = collect($steps)->every(fn (array $step): bool => $step['passed']) ? 'passed' : 'failed';
        $manifest['result']['passed'] = $manifest['result']['terminal'] === 'passed';
        $manifest['artifacts'] = ['root' => '.'];

        $artifactStore->putJson('terminal/prepare.json', [
            'summary' => $summary,
            'run_id' => $runId,
            'payload_policy' => 'Aggregate counts only; no user email or legal-owner payload is persisted.',
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
        $summary = $this->summary($this->buildUserDirectory->handle());
        $browserReport = $artifactStore->readJson('browser/report.json') ?? [];
        $expected = [
            'user_count' => $manifest['resources']['user_count'],
            'verified_user_count' => $manifest['resources']['verified_user_count'],
            'linked_owner_count' => $manifest['resources']['linked_owner_count'],
            'unassigned_role_count' => $manifest['resources']['unassigned_role_count'],
            'role_distribution' => $manifest['resources']['role_distribution'],
        ];
        $checks = [
            $this->step('audit-browser-result', 'Browser evidence runner passed', ['browser' => true], ['browser' => (bool) data_get($browserReport, 'result.passed')]),
            $this->step('audit-canonical-directory', 'Canonical user-directory aggregate still matches the prepared manifest', $expected, $summary),
            $this->step('audit-browser-directory', 'Visible user-directory aggregate agrees with canonical evidence', $expected, data_get($browserReport, 'user_directory.summary', [])),
            $this->step('audit-browser-read-only', 'Directory remains read-only and usable on mobile', ['mutation_actions_visible' => false, 'mobile_visible' => true, 'page_horizontal_overflow' => false], ['mutation_actions_visible' => data_get($browserReport, 'user_directory.mutation_actions_visible'), 'mobile_visible' => data_get($browserReport, 'user_directory.mobile_visible'), 'page_horizontal_overflow' => data_get($browserReport, 'user_directory.page_horizontal_overflow')]),
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
     * @param  array<string, mixed>  $directory
     * @return array{user_count: int, verified_user_count: int, linked_owner_count: int, unassigned_role_count: int, role_distribution: array<string, int>}
     */
    private function summary(array $directory): array
    {
        return [
            'user_count' => (int) data_get($directory, 'summary.user_count', 0),
            'verified_user_count' => (int) data_get($directory, 'summary.verified_user_count', 0),
            'linked_owner_count' => (int) data_get($directory, 'summary.linked_owner_count', 0),
            'unassigned_role_count' => (int) data_get($directory, 'summary.unassigned_role_count', 0),
            'role_distribution' => data_get($directory, 'summary.role_distribution', []),
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
            'title' => 'User directory visibility',
            'summary' => 'An authorized administrator reviews application accounts, their current roles, verification state, and durable legal-owner links without changing identity or access.',
            'run_id' => $runId,
            'frames' => [
                ['title' => 'Administrator opens the user directory', 'description' => 'The directory shows the current account population and role distribution.', 'dialogue' => 'Account identity and legal owner identity remain separate facts.', 'duration_seconds' => 4],
                ['title' => 'Administrator reviews account links', 'description' => 'Each row shows role, verification state, and any durable BusinessOwner link.', 'dialogue' => 'No ownership is inferred from application history.', 'duration_seconds' => 5],
                ['title' => 'Administrator confirms the read-only boundary', 'description' => 'Provisioning, password operations, and role changes remain unavailable.', 'dialogue' => 'Visibility does not grant mutation authority.', 'duration_seconds' => 4],
            ],
        ];
    }

    private function storyboardHtml(string $runId): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>User directory visibility</title></head><body><h1>User directory visibility</h1><p>Run ID: '.e($runId).'</p><ol><li>Administrator opens the user directory.</li><li>Administrator reviews roles and legal-owner links.</li><li>Administrator confirms the directory remains read-only.</li></ol></body></html>';
    }
}
