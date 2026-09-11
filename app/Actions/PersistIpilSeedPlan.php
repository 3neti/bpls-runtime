<?php

namespace App\Actions;

use App\Support\IpilRescue\CanonicalJson;
use App\Support\IpilRescue\IpilSeedPlan;
use RuntimeException;

final class PersistIpilSeedPlan
{
    /** @return array{directory: string, plan: string, execution_manifest: string} */
    public function handle(IpilSeedPlan $plan): array
    {
        $planId = $plan->toArray()['plan_id'];
        $root = storage_path('app/private/ipil-rescue/plans');
        $directory = $root.'/'.$planId;

        if (is_link($root) || is_link($directory)) {
            throw new RuntimeException('Seed plan storage cannot use symbolic links.');
        }

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the private seed plan directory.');
        }

        $paths = [
            'plan' => $directory.'/seed-plan.json',
            'execution_manifest' => $directory.'/seed-execution-manifest.json',
        ];
        $payloads = [
            'plan' => CanonicalJson::encode($plan->toArray())."\n",
            'execution_manifest' => CanonicalJson::encode($plan->executionManifest())."\n",
        ];

        foreach ($paths as $key => $path) {
            if (is_file($path)) {
                $existing = json_decode((string) file_get_contents($path), true);
                $expectedFingerprint = $key === 'plan' ? $plan->fingerprint : $plan->executionManifest()['semantic_fingerprint_sha256'];
                if (! is_array($existing) || ! hash_equals($expectedFingerprint, (string) ($existing['semantic_fingerprint_sha256'] ?? ''))) {
                    throw new RuntimeException('An existing seed plan artifact differs; immutable artifact replacement is forbidden.');
                }

                continue;
            }

            if (file_put_contents($path, $payloads[$key], LOCK_EX) === false) {
                throw new RuntimeException('Unable to persist a private seed plan artifact.');
            }
            chmod($path, 0600);
        }

        return ['directory' => $directory] + $paths;
    }
}
