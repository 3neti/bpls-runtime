<?php

namespace App\Assessment;

use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Models\FeeRule;
use App\Models\PermitApplication;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Validation\ValidationException;

class ProvisionalTreasuryEnterpriseSchedule
{
    public const FeeCode = 'IPIL-LEGACY-5F028B76EEBEF485';

    /** Exact New-applicable Mayor fee identities; the application guard excludes Renewal and production. */
    public const ManualFeeCodes = [
        self::FeeCode, 'IPIL-LEGACY-6AB4BE2893A5E40D', 'IPIL-LEGACY-E2080C485D71B3FA',
        'IPIL-LEGACY-CB590436779EF57F', 'IPIL-LEGACY-A7C5089506E8F612', 'IPIL-LEGACY-AFF802C5E37C9560',
        'IPIL-LEGACY-DA24F9A360151D6B', 'IPIL-LEGACY-AA02C719FB66BEED', 'IPIL-LEGACY-862E8AD7476CA829',
        'IPIL-LEGACY-347A966FE4EF8B07', 'IPIL-LEGACY-0D95E808887A61FA', 'IPIL-LEGACY-A55AC5CA07472003',
        'IPIL-LEGACY-45064A5BCB55E36D', 'IPIL-LEGACY-92CA6C3F4A4832CB', 'IPIL-LEGACY-A051655E2D2F3998',
        'IPIL-LEGACY-4EFC975F6E46D995',
    ];

    /** @return array<string, mixed>|null */
    public function forApplication(PermitApplication $application, FeeRule $rule): ?array
    {
        if (! in_array($rule->code, self::ManualFeeCodes, true) || $rule->basis !== 'legacy_unresolved'
            || $application->type !== PermitApplicationType::New || $application->application_year !== 2026
            || $application->isHistoricalEvidenceOnly()
            || data_get($application->metadata, 'nelson_reconciliation_v1.commissioned_path') !== true
            || config('stakeholder_preview.mode') !== true
            || config('stakeholder_preview.production_migration_enabled') !== false
            || config('stakeholder_preview.production_integrations') !== 'disabled'
            || config('treasury_enterprise.provisional_uat_enabled') !== true
            || ! in_array(config('treasury_enterprise.provisional_uat_context'), config('treasury_enterprise.provisional_uat_allowed_contexts', []), true)) {
            return null;
        }

        $schedule = config('treasury_enterprise.schedule');
        if (! is_array($schedule) || empty($schedule['id']) || empty($schedule['version'])
            || empty($schedule['authority']) || empty($schedule['source'])
            || ($schedule['policy_status'] ?? null) !== 'provisional_uat_pending_municipal_confirmation'
            || ($schedule['currency'] ?? null) !== 'PHP'
            || ! is_array($schedule['bands'] ?? null)
            || array_keys($schedule['bands']) !== ['Micro', 'Cottage', 'Small', 'Medium', 'Large']) {
            return null;
        }
        foreach ($schedule['bands'] as $amount) {
            if (! is_int($amount) || $amount <= 0) {
                return null;
            }
        }

        $default = data_get($rule->metadata, 'treasury_entry_default');
        $manualAvailable = app(StakeholderPreviewSafety::class)->isEnabled()
            && app()->environment(['local', 'testing', 'staging', 'uat']);
        if ($rule->code !== self::FeeCode) {
            if (! $manualAvailable) {
                return null;
            }
            $schedule = [
                'id' => 'manual-new-mayor-test-determination',
                'version' => '2026-09-21.v1',
                'policy_status' => 'test_only_not_municipal_policy',
                'currency' => 'PHP',
                'fee_code' => $rule->code,
                'bands' => [],
            ];
        }
        if ($manualAvailable && is_array($default)
            && ($default['application_year'] ?? null) === $application->application_year
            && ($default['application_type'] ?? null) === $application->type->value
            && is_int($default['amount_minor'] ?? null)
            && $default['amount_minor'] > 0 && $default['amount_minor'] <= 1000000000
            && is_string($default['version'] ?? null) && trim($default['version']) !== ''
            && is_string($default['reference'] ?? null) && trim($default['reference']) !== '') {
            $schedule['entry_default'] = $default;
        }

        return [...$schedule,
            'fingerprint' => hash('sha256', json_encode($schedule, JSON_THROW_ON_ERROR)),
            'manual_determination_available' => $manualAvailable,
        ];
    }

    /** @param array{line_of_business_id: int, enterprise_classification?: string|null, enterprise_schedule_fingerprint?: string|null, manual_amount_cents?: int, manual_basis?: string, items: list<array<string, mixed>>} $selection
     * @return array<string, mixed>|null
     */
    public function determine(PermitApplication $application, FeeRule $rule, array $selection, User $actor): ?array
    {
        $schedule = $this->forApplication($application, $rule);
        if (array_key_exists('manual_amount_cents', $selection) || array_key_exists('manual_basis', $selection)) {
            $amount = $selection['manual_amount_cents'] ?? null;
            $basis = $selection['manual_basis'] ?? null;
            $item = collect($selection['items'])->firstWhere('fee_rule_id', $rule->id);
            if (($schedule['manual_determination_available'] ?? false) !== true
                || ! $actor->can(UserPermission::CorrectEvaluationLinesOfBusiness->value)
                || ! is_int($amount) || $amount < 1 || $amount > 1000000000
                || ! is_string($basis) || trim($basis) === '' || mb_strlen($basis) > 1000
                || filled($selection['enterprise_classification'] ?? null)
                || ($selection['enterprise_schedule_fingerprint'] ?? null) !== $schedule['fingerprint']
                || $item === null || ($item['amount_cents'] ?? null) !== $amount) {
                throw ValidationException::withMessages(['selections' => 'A manual Mayor’s Permit test determination requires an authorized Treasury officer, a positive amount, a recorded basis and the current local/UAT context. The required item cannot be omitted or changed independently.']);
            }

            return [
                'application_id' => $application->id,
                'application_year' => $application->application_year,
                'line_of_business_id' => $selection['line_of_business_id'],
                'classification' => null,
                'determination_source' => 'manual_treasury_test_determination',
                'basis' => trim($basis),
                'derived_from_applicant_data' => false,
                'determined_by_id' => $actor->id,
                'determined_at' => now()->toIso8601String(),
                'schedule' => [
                    'id' => 'manual-treasury-test-determination',
                    'version' => '2026-09-20.v1',
                    'policy_status' => 'test_only_not_municipal_policy',
                    'currency' => 'PHP',
                    'reviewed_catalogue_fingerprint' => $schedule['fingerprint'],
                    'reviewed_entry_default' => $schedule['entry_default'] ?? null,
                ],
                'resulting_amount_cents' => $amount,
                'fee_rule_id' => $rule->id,
                'production_policy_authority' => false,
            ];
        }
        if ($schedule === null) {
            return null;
        }
        $classification = $selection['enterprise_classification'] ?? null;
        if (! is_string($classification) || ! array_key_exists($classification, $schedule['bands'])
            || ($selection['enterprise_schedule_fingerprint'] ?? null) !== $schedule['fingerprint']) {
            throw ValidationException::withMessages(['selections' => 'Choose a valid Enterprise Classification and review the current provisional UAT schedule before confirming Treasury.']);
        }
        $item = collect($selection['items'])->firstWhere('fee_rule_id', $rule->id);
        $amount = $schedule['bands'][$classification];
        if ($item === null || ($item['amount_cents'] ?? null) !== $amount) {
            throw ValidationException::withMessages(['selections' => 'The Mayor’s Permit Fee must match the enterprise classification schedule and cannot be omitted or overridden.']);
        }

        return [
            'application_id' => $application->id,
            'line_of_business_id' => $selection['line_of_business_id'],
            'classification' => $classification,
            'determination_source' => 'explicit_treasury_officer_selection',
            'derived_from_applicant_data' => false,
            'determined_by_id' => $actor->id,
            'determined_at' => now()->toIso8601String(),
            'schedule' => $schedule,
            'resulting_amount_cents' => $amount,
            'fee_rule_id' => $rule->id,
            'production_policy_authority' => false,
        ];
    }
}
