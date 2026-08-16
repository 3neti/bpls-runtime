<?php

namespace App\LifecycleScenarios;

use App\Actions\BuildRolePermissionMatrix;
use App\Enums\UserPermission;
use App\Models\User;
use RuntimeException;

final class RolePermissionMatrixVisibilityScenario
{
    public function __construct(
        private readonly BuildRolePermissionMatrix $buildRolePermissionMatrix,
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
        $matrix = $this->buildRolePermissionMatrix->handle();
        $roles = $this->roles($matrix);
        $manifest = $this->scenarioManifest->initial($scenario, $runId, $actors);
        $roleEvidence = $this->roleEvidence($matrix);
        $steps = [
            $this->step('operator-authorized', 'Resolve an operator through the real role and permission boundary', ['can_view_roles' => true], ['can_view_roles' => $operator->can(UserPermission::ViewRoles->value)]),
            $this->step('authorization-matrix-projected', 'Project effective role access from canonical roles, assignments, and runtime Admin semantics', ['role_count' => data_get($matrix, 'summary.role_count'), 'permission_count' => count(UserPermission::cases())], ['role_count' => count($matrix['roles']), 'permission_count' => count($matrix['permissions'])]),
            $this->step('permission-catalog-drift-measured', 'Measure permission catalog drift without changing role assignments', ['missing_permission_count' => data_get($matrix, 'summary.missing_permission_count'), 'unknown_permission_count' => data_get($matrix, 'summary.unknown_permission_count')], ['missing_permission_count' => count(data_get($matrix, 'catalog_drift.missing_permission_codes', [])), 'unknown_permission_count' => count(data_get($matrix, 'catalog_drift.unknown_permission_codes', []))]),
        ];

        foreach ($steps as $step) {
            $artifactStore->appendJsonLine('terminal/action-log.jsonl', $step);
        }

        $manifest['resources'] = [
            'record_type' => 'authorization_matrix',
            'record_id' => $operator->role_id,
            'public_reference' => 'Current role and permission matrix',
            'matrix_url' => route('staff.roles.index', absolute: false),
            'role_count' => data_get($matrix, 'summary.role_count'),
            'permission_count' => data_get($matrix, 'summary.canonical_permission_count'),
            'catalog_in_sync' => data_get($matrix, 'summary.catalog_in_sync'),
            'missing_permission_count' => data_get($matrix, 'summary.missing_permission_count'),
            'unknown_permission_count' => data_get($matrix, 'summary.unknown_permission_count'),
            'admin_override_role_count' => count(array_filter(
                $roles,
                fn (array $role): bool => ($role['access_mode'] ?? null) === 'admin_override',
            )),
            'role_evidence' => $roleEvidence,
        ];
        $manifest['steps'] = $steps;
        $manifest['result']['terminal'] = collect($steps)->every(fn (array $step): bool => $step['passed']) ? 'passed' : 'failed';
        $manifest['result']['passed'] = $manifest['result']['terminal'] === 'passed';
        $manifest['artifacts'] = ['root' => '.'];

        $artifactStore->putJson('terminal/prepare.json', [
            'summary' => $matrix['summary'],
            'catalog_drift' => $matrix['catalog_drift'],
            'role_evidence' => $roleEvidence,
            'run_id' => $runId,
        ]);
        $artifactStore->putJson('terminal/execution.json', [
            'steps' => $steps,
            'read_only' => true,
            'external_calls' => 0,
            'irreversible_actions' => false,
            'notifications' => false,
        ]);
        $artifactStore->putJson('storyboard/storyboard.json', $this->storyboard($runId, $manifest['resources']));
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
        $matrix = $this->buildRolePermissionMatrix->handle();
        $browserReport = $artifactStore->readJson('browser/report.json') ?? [];
        $roleEvidence = $this->roleEvidence($matrix);
        $checks = [
            $this->step('audit-browser-result', 'Browser evidence runner passed', ['browser' => true], ['browser' => (bool) data_get($browserReport, 'result.passed')]),
            $this->step('audit-canonical-matrix', 'Canonical role and permission projection still matches the prepared manifest', ['role_count' => $manifest['resources']['role_count'], 'permission_count' => $manifest['resources']['permission_count'], 'role_evidence' => $manifest['resources']['role_evidence']], ['role_count' => data_get($matrix, 'summary.role_count'), 'permission_count' => data_get($matrix, 'summary.canonical_permission_count'), 'role_evidence' => $roleEvidence]),
            $this->step('audit-browser-matrix', 'Desktop UI agrees with canonical role, permission, and catalog evidence', ['role_count' => $manifest['resources']['role_count'], 'permission_count' => $manifest['resources']['permission_count'], 'catalog_in_sync' => $manifest['resources']['catalog_in_sync'], 'admin_override_role_count' => $manifest['resources']['admin_override_role_count'], 'role_evidence' => $manifest['resources']['role_evidence']], ['role_count' => data_get($browserReport, 'role_permissions.role_count'), 'permission_count' => data_get($browserReport, 'role_permissions.permission_count'), 'catalog_in_sync' => data_get($browserReport, 'role_permissions.catalog_in_sync'), 'admin_override_role_count' => data_get($browserReport, 'role_permissions.admin_override_role_count'), 'role_evidence' => data_get($browserReport, 'role_permissions.role_evidence')]),
            $this->step('audit-browser-read-only', 'Authorization matrix remains read-only and usable on mobile', ['mutation_actions_visible' => false, 'mobile_visible' => true, 'page_horizontal_overflow' => false], ['mutation_actions_visible' => data_get($browserReport, 'role_permissions.mutation_actions_visible'), 'mobile_visible' => data_get($browserReport, 'role_permissions.mobile_visible'), 'page_horizontal_overflow' => data_get($browserReport, 'role_permissions.page_horizontal_overflow')]),
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
            'canonical' => [
                'summary' => $matrix['summary'],
                'catalog_drift' => $matrix['catalog_drift'],
                'role_evidence' => $roleEvidence,
            ],
            'browser' => $browserReport,
        ]);
        $artifactStore->putJson('manifest.json', $manifest);
        $artifactStore->put('summary.html', $this->summaryRenderer->html($manifest));

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $matrix
     * @return array<int, array<string, mixed>>
     */
    private function roleEvidence(array $matrix): array
    {
        return collect($this->roles($matrix))
            ->map(fn (array $role): array => [
                'id' => $role['id'],
                'code' => $role['code'],
                'user_count' => $role['user_count'],
                'access_mode' => $role['access_mode'],
                'assigned_permission_count' => $role['assigned_permission_count'],
                'effective_permission_count' => $role['effective_permission_count'],
                'unknown_assigned_permission_count' => count($role['unknown_assigned_permission_codes']),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $matrix
     * @return array<int, array<string, mixed>>
     */
    private function roles(array $matrix): array
    {
        $roles = $matrix['roles'] ?? [];

        if (! is_array($roles)) {
            return [];
        }

        return array_values(array_filter($roles, is_array(...)));
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

    /**
     * @param  array<string, mixed>  $resources
     * @return array<string, mixed>
     */
    private function storyboard(string $runId, array $resources): array
    {
        return [
            'title' => 'Role and permission matrix visibility',
            'summary' => 'An authorized administrator reviews the current effective access of every role and sees whether stored permission rows agree with the runtime permission catalog.',
            'run_id' => $runId,
            'record' => [
                'type' => 'authorization_matrix',
                'reference' => $resources['public_reference'],
            ],
            'frames' => [
                [
                    'title' => 'Administrator opens roles and permissions',
                    'description' => 'The system shows current roles, assigned users, and effective access.',
                    'dialogue' => 'Access is projected from the same authorization rules used by the application.',
                    'duration_seconds' => 4,
                ],
                [
                    'title' => 'Administrator reviews effective access',
                    'description' => 'Assigned permissions and the Admin role override remain visibly distinct.',
                    'dialogue' => 'The matrix does not imply that an override came from an assignment row.',
                    'duration_seconds' => 5,
                ],
                [
                    'title' => 'Administrator checks catalog integrity',
                    'description' => 'Missing or unknown permission rows are visible without changing authorization data.',
                    'dialogue' => 'Role changes remain outside this read-only verification journey.',
                    'duration_seconds' => 4,
                ],
            ],
        ];
    }

    private function storyboardHtml(string $runId): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Role and permission matrix visibility</title></head><body><h1>Role and permission matrix visibility</h1><p>Run ID: '.e($runId).'</p><ol><li>Administrator opens roles and permissions.</li><li>Administrator reviews effective access.</li><li>Administrator checks catalog integrity without changing authorization data.</li></ol></body></html>';
    }
}
