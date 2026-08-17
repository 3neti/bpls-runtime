<?php

namespace App\Actions;

use RuntimeException;

final class VerifyFinancialCalibrationSuite
{
    public const ManifestSchemaVersion = 'bpls.financial-calibration-specimen.v1';

    public const ReportSchemaVersion = 'bpls.financial-calibration-suite.v1';

    private const EvidenceLayers = [
        'revenue_code',
        'production_configuration',
        'legacy_evaluator',
        'persisted_outcome',
        'municipal_specimen',
    ];

    private const ForbiddenKeys = [
        'application_number',
        'authorization_header',
        'business_name',
        'cookie',
        'email',
        'mobile_number',
        'owner_name',
        'password',
        'pin',
        'raw_source_id',
        'receipt_number',
        'session_token',
        'signature',
        'storage_id',
        'transaction_number',
    ];

    /**
     * @param  list<array<string, mixed>>  $manifests
     * @return array<string, mixed>
     */
    public function handle(array $manifests, string $runReference): array
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Financial calibration verification is restricted to local or testing environments.');
        }
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,100}$/', $runReference)) {
            throw new RuntimeException('A stable filesystem-safe run reference is required.');
        }
        if ($manifests === []) {
            throw new RuntimeException('At least one private financial calibration manifest is required.');
        }

        $specimens = array_map(fn (array $manifest): array => $this->verifySpecimen($manifest), $manifests);
        usort($specimens, fn (array $left, array $right): int => $left['calibration_id'] <=> $right['calibration_id']);

        $historicalPassed = count(array_filter($specimens, fn (array $specimen): bool => $specimen['historical_reproduction']['passed']));
        $historicalFailed = count($specimens) - $historicalPassed;
        $policyPending = array_sum(array_column(array_column($specimens, 'future_policy'), 'pending_count'));
        $policyAccepted = array_sum(array_column(array_column($specimens, 'future_policy'), 'accepted_count'));
        $policyRejected = array_sum(array_column(array_column($specimens, 'future_policy'), 'rejected_count'));

        return [
            'schema_version' => self::ReportSchemaVersion,
            'run_id' => $runReference,
            'summary' => [
                'specimen_count' => count($specimens),
                'historical_reproduction_passed' => $historicalPassed,
                'historical_reproduction_failed' => $historicalFailed,
                'future_policy_pending' => $policyPending,
                'future_policy_accepted' => $policyAccepted,
                'future_policy_rejected' => $policyRejected,
                'historical_suite_passed' => $historicalFailed === 0,
                'future_policy_suite_status' => $policyPending > 0 ? 'blocked_pending_authority' : 'authority_decisions_recorded',
            ],
            'specimens' => $specimens,
            'safety' => [
                'formulas_evaluated' => false,
                'historical_liability_recalculated' => false,
                'historical_values_rewritten' => false,
                'future_policy_assertions_executed' => false,
                'financial_policy_activated' => false,
                'financial_domain_writes' => false,
                'migration_executed' => false,
                'cutover_authorized' => false,
            ],
        ];
    }

    /** @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    private function verifySpecimen(array $manifest): array
    {
        $this->assertNoSensitiveKeys($manifest);
        if (($manifest['schema_version'] ?? null) !== self::ManifestSchemaVersion) {
            throw new RuntimeException('Financial calibration manifest schema is unsupported.');
        }

        $calibrationId = $this->requiredString($manifest, 'calibration_id');
        if (! preg_match('/^CAL-[0-9]{4}-[0-9]{3,}$/', $calibrationId)) {
            throw new RuntimeException('Financial calibration ID must use the CAL-YYYY-NNN format.');
        }
        if (($manifest['classification'] ?? null) !== 'golden_financial_specimen') {
            throw new RuntimeException("Calibration [{$calibrationId}] is not classified as a golden financial specimen.");
        }

        $layers = $this->requiredArray($manifest, 'evidence_layers');
        foreach (self::EvidenceLayers as $layer) {
            $evidence = $layers[$layer] ?? null;
            if (! is_array($evidence) || $this->string($evidence['status'] ?? null) === '') {
                throw new RuntimeException("Calibration [{$calibrationId}] is missing evidence layer [{$layer}].");
            }
            $references = $evidence['references'] ?? null;
            if (! is_array($references) || $references === [] || array_filter($references, fn (mixed $reference): bool => $this->string($reference) === '') !== []) {
                throw new RuntimeException("Calibration [{$calibrationId}] evidence layer [{$layer}] requires non-empty references.");
            }
        }

        $historicalAssertions = $this->list($manifest, 'historical_reproduction_assertions');
        if ($historicalAssertions === []) {
            throw new RuntimeException("Calibration [{$calibrationId}] requires historical reproduction assertions.");
        }
        $historical = array_map(fn (array $assertion): array => $this->verifyHistoricalAssertion($calibrationId, $assertion), $historicalAssertions);

        $policyAssertions = $this->list($manifest, 'future_policy_assertions');
        if ($policyAssertions === []) {
            throw new RuntimeException("Calibration [{$calibrationId}] requires separately declared future policy assertions.");
        }
        $policy = array_map(fn (array $assertion): array => $this->verifyPolicyAssertion($calibrationId, $assertion), $policyAssertions);

        $pending = count(array_filter($policy, fn (array $assertion): bool => $assertion['authorization_status'] === 'pending'));
        $accepted = count(array_filter($policy, fn (array $assertion): bool => $assertion['authorization_status'] === 'accepted'));
        $rejected = count($policy) - $pending - $accepted;

        return [
            'calibration_id' => $calibrationId,
            'classification' => 'golden_financial_specimen',
            'specimen_sha256' => $this->sha256($manifest, 'specimen_sha256'),
            'production_snapshot_sha256' => $this->sha256($manifest, 'production_snapshot_sha256'),
            'evidence_layers' => $layers,
            'historical_reproduction' => [
                'assertion_count' => count($historical),
                'passed_count' => count(array_filter($historical, fn (array $assertion): bool => $assertion['passed'])),
                'passed' => array_filter($historical, fn (array $assertion): bool => ! $assertion['passed']) === [],
                'assertions' => $historical,
            ],
            'future_policy' => [
                'assertion_count' => count($policy),
                'pending_count' => $pending,
                'accepted_count' => $accepted,
                'rejected_count' => $rejected,
                'suite_status' => $pending > 0 ? 'blocked_pending_authority' : 'authority_decisions_recorded',
                'assertions_executed' => false,
                'assertions' => $policy,
            ],
            'historical_divergences' => $this->list($manifest, 'historical_divergences'),
        ];
    }

    /** @param array<string, mixed> $assertion
     * @return array<string, mixed>
     */
    private function verifyHistoricalAssertion(string $calibrationId, array $assertion): array
    {
        $key = $this->requiredString($assertion, 'key');
        $expected = $this->requiredString($assertion, 'expected');
        $actual = $this->requiredString($assertion, 'actual');
        $evidenceLayers = $assertion['evidence_layers'] ?? null;
        if (! is_array($evidenceLayers) || $evidenceLayers === []) {
            throw new RuntimeException("Historical assertion [{$calibrationId}:{$key}] requires evidence layers.");
        }
        foreach ($evidenceLayers as $layer) {
            if (! is_string($layer) || ! in_array($layer, self::EvidenceLayers, true)) {
                throw new RuntimeException("Historical assertion [{$calibrationId}:{$key}] references an unsupported evidence layer.");
            }
        }

        return [
            'key' => $key,
            'unit' => $this->requiredString($assertion, 'unit'),
            'expected' => $expected,
            'actual' => $actual,
            'passed' => hash_equals($expected, $actual),
            'evidence_layers' => array_values(array_unique($evidenceLayers)),
        ];
    }

    /** @param array<string, mixed> $assertion
     * @return array<string, mixed>
     */
    private function verifyPolicyAssertion(string $calibrationId, array $assertion): array
    {
        $key = $this->requiredString($assertion, 'key');
        $status = $this->requiredString($assertion, 'authorization_status');
        if (! in_array($status, ['pending', 'accepted', 'rejected'], true)) {
            throw new RuntimeException("Future policy assertion [{$calibrationId}:{$key}] has an invalid authorization status.");
        }

        $authority = $assertion['authority'] ?? null;
        if ($status === 'accepted') {
            if (! is_array($authority)) {
                throw new RuntimeException("Accepted future policy assertion [{$calibrationId}:{$key}] requires authority evidence.");
            }
            foreach (['decision_reference', 'authority_role', 'decided_at'] as $field) {
                $this->requiredString($authority, $field);
            }
        }

        return [
            'key' => $key,
            'authorization_status' => $status,
            'authority' => is_array($authority) ? $authority : null,
            'executed' => false,
        ];
    }

    /** @param array<string, mixed> $values */
    private function requiredString(array $values, string $key): string
    {
        $value = $this->string($values[$key] ?? null);
        if ($value === '') {
            throw new RuntimeException("Required financial calibration field [{$key}] is missing.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function requiredArray(array $values, string $key): array
    {
        $value = $values[$key] ?? null;
        if (! is_array($value)) {
            throw new RuntimeException("Required financial calibration object [{$key}] is missing.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values
     * @return list<array<string, mixed>>
     */
    private function list(array $values, string $key): array
    {
        $items = $values[$key] ?? null;
        if (! is_array($items) || ! array_is_list($items)) {
            throw new RuntimeException("Financial calibration field [{$key}] must be a list.");
        }
        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new RuntimeException("Financial calibration field [{$key}] contains an invalid item.");
            }
        }

        return $items;
    }

    /** @param array<string, mixed> $values */
    private function sha256(array $values, string $key): string
    {
        $value = $this->requiredString($values, $key);
        if (! preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new RuntimeException("Financial calibration field [{$key}] must be a lowercase SHA-256 hash.");
        }

        return $value;
    }

    /** @param array<array-key, mixed> $values */
    private function assertNoSensitiveKeys(array $values, string $path = ''): void
    {
        foreach ($values as $key => $value) {
            $field = is_string($key) ? $key : (string) $key;
            $currentPath = $path === '' ? $field : $path.'.'.$field;
            if (in_array($field, self::ForbiddenKeys, true)) {
                throw new RuntimeException("Financial calibration manifest contains forbidden sensitive field [{$currentPath}].");
            }
            if (is_array($value)) {
                $this->assertNoSensitiveKeys($value, $currentPath);
            }
        }
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
