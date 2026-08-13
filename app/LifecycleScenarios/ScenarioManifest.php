<?php

namespace App\LifecycleScenarios;

use App\Models\User;
use Illuminate\Support\Str;

final class ScenarioManifest
{
    /**
     * @param  array<string, User>  $actors
     * @return array<string, mixed>
     */
    public function initial(LifecycleScenarioDefinition $scenario, string $runId, array $actors): array
    {
        return [
            'schema_version' => 'application.lifecycle-evidence.v1',
            'run_id' => $runId,
            'scenario' => [
                'key' => $scenario->key,
                'label' => $scenario->label,
                'mode' => $scenario->mode,
            ],
            'environment' => app()->environment(),
            'safety' => $scenario->safety + [
                'risk' => $scenario->risk,
            ],
            'actors' => collect($actors)
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $this->maskEmail($user->email),
                    'role' => $user->role?->code,
                ])
                ->all(),
            'resources' => [],
            'steps' => [],
            'expectations' => $scenario->expectations,
            'result' => [
                'passed' => false,
                'terminal' => null,
                'browser' => null,
                'audit' => null,
            ],
            'artifacts' => [],
            'created_at' => now()->toIso8601String(),
        ];
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return Str::substr($local, 0, 1).'***@'.$domain;
    }
}
