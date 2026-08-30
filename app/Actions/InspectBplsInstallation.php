<?php

namespace App\Actions;

use App\Assessment\ApplicableFeeRuleQuery;
use App\Enums\FeeRuleExecutionStatus;
use App\Enums\FeeRulePublicationSource;
use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\AssessmentDecision;
use App\Models\AssessmentLine;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationCounterCheck;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\BusinessPermitEvaluationItemRevision;
use App\Models\BusinessPermitEvaluationVersion;
use App\Models\CollectionAllocation;
use App\Models\FeeRule;
use App\Models\InstitutionalPosition;
use App\Models\OfficeChargeContribution;
use App\Models\PaymentSchedule;
use App\Models\PaymentScheduleLine;
use App\Models\Permission;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\PermitClearance;
use App\Models\ProvisionalUatPermitCompletion;
use App\Models\Receipt;
use App\Models\RevenueCodeProvision;
use App\Models\Role;
use App\Models\TreasuryCollection;
use App\Models\User;
use Illuminate\Support\Collection;

class InspectBplsInstallation
{
    public const string InspectionFeeCode = 'MRC-3A-04-BUSINESS-INSPECTION';

    public function __construct(
        private readonly BuildMunicipalPriceList $buildPriceList,
        private readonly ApplicableFeeRuleQuery $applicableFeeRules,
        private readonly EnsureBplsInstitution $institution,
    ) {}

    /** @return array<string, mixed> */
    public function handle(): array
    {
        $feeRules = FeeRule::query()
            ->with(['ranges', 'currentReconciliation'])
            ->orderBy('code')
            ->get();
        $acceptedInspectionRules = $feeRules->where('code', self::InspectionFeeCode)->values();
        $publicPriceList = $this->buildPriceList->handle();
        $publishedCharges = $this->publishedCharges($publicPriceList);
        $uniquePublishedCharges = $publishedCharges->unique('traceability.rule_code')->values();
        $syntheticPublishedCharges = $publishedCharges->filter(
            fn (array $charge): bool => $charge['traceability']['source_classification'] !== FeeRulePublicationSource::AcceptedMunicipalAuthority->value,
        )->values();
        $syntheticRules = $feeRules->filter(fn (FeeRule $rule): bool => in_array(
            FeeRulePublicationSource::forRule($rule),
            [
                FeeRulePublicationSource::Synthetic,
                FeeRulePublicationSource::ProvisionalUat,
                FeeRulePublicationSource::Historical,
                FeeRulePublicationSource::Mock,
                FeeRulePublicationSource::LegacyEvidenceOnly,
                FeeRulePublicationSource::LifecycleTest,
            ],
            true,
        ));
        $blockedRules = $feeRules->filter(
            fn (FeeRule $rule): bool => $rule->currentReconciliation?->execution_status === FeeRuleExecutionStatus::Blocked,
        )->values();
        $assessmentParity = $this->assessmentParity();
        $roles = $this->roles();
        $positions = $this->positions();
        $transactionCounts = $this->transactionCounts();
        $issues = $this->integrityIssues(
            $acceptedInspectionRules,
            $uniquePublishedCharges,
            $syntheticPublishedCharges,
            $syntheticRules,
            $assessmentParity,
            $roles,
            $positions,
        );

        $pricePayload = [
            'in_force' => $uniquePublishedCharges->map(fn (array $charge): array => [
                'code' => $charge['traceability']['rule_code'],
                'name' => $charge['label'],
                'amount_cents' => $charge['amount_cents'],
                'cadence' => $charge['cadence'],
                'legal_basis' => $charge['traceability']['legal_basis'],
                'execution_status' => $charge['traceability']['execution_status'],
                'used_by_assessment' => true,
            ])->all(),
            'recorded_confirmation_required' => $blockedRules->map(fn (FeeRule $rule): array => [
                'code' => $rule->code,
                'name' => $rule->name,
                'classification' => FeeRulePublicationSource::forRule($rule)->value,
                'execution_status' => $rule->currentReconciliation?->execution_status->value,
            ])->all(),
            'determined_during_municipal_evaluation' => [
                'Engineering', 'MPDO / MPDC', 'Assessor', 'Health', 'MENRO',
            ],
            'not_commissioned_provision_count' => RevenueCodeProvision::query()->count(),
            'published_exact_occurrence_count' => $publishedCharges->count(),
            'published_unique_exact_rule_count' => $uniquePublishedCharges->count(),
            'synthetic_uat_exact_published_count' => $syntheticPublishedCharges->count(),
            'synthetic_uat_fee_rule_count' => $syntheticRules->count(),
            'coherent' => $issues === [],
            'assessment_parity' => $assessmentParity,
        ];

        $commissioningAdmin = $this->commissioningAdministrator();
        $semanticPayload = [
            'schema_version' => (string) config('bpls_installation.schema_version'),
            'municipality' => [
                'name' => (string) config('municipality.name'),
                'province' => (string) config('municipality.province'),
                'system_name' => (string) config('municipality.system_name'),
            ],
            'price_list' => $pricePayload,
            'roles' => $roles,
            'positions' => $positions,
            'commissioning_administrator' => [
                'role_code' => UserRole::Admin->value,
                'envelope_installed' => Role::query()->where('code', UserRole::Admin->value)->exists(),
                'provisioning_status' => $commissioningAdmin['status'],
            ],
            'commissioning' => [
                'technical_baseline' => $issues === [] ? 'pass' : 'fail',
                'price_list_coherence' => $issues === [] ? 'pass' : 'fail',
                'assessment_capability' => $assessmentParity['pass'] ? 'commissioned_paths_available' : 'fail',
                'authority_assignment' => 'positions_installed_named_assignments_unverified',
                'payment_capability' => 'software_capability_installed_authority_assignment_required',
                'permit_release' => 'not_commissioned',
            ],
        ];

        return [
            ...$semanticPayload,
            'zero_state' => [
                'is_empty' => collect($transactionCounts)->every(fn (int $count): bool => $count === 0),
                'counts' => $transactionCounts,
            ],
            'integrity' => ['pass' => $issues === [], 'issues' => $issues],
            'fingerprints' => [
                'price_list_sha256' => $this->fingerprint($pricePayload),
                'installation_sha256' => $this->fingerprint($semanticPayload),
            ],
            'evidence' => [
                'database_driver' => (string) config('database.default'),
                'checked_at' => now()->toIso8601String(),
                'manifest_path' => 'storage/app/private/bpls-installation/manifest.json',
            ],
        ];
    }

    /** @return array{pass: bool, new: bool, renewal: bool} */
    private function assessmentParity(): array
    {
        $containsExactlyOneInspection = fn (PermitApplicationType $type): bool => $this->applicableFeeRules
            ->forApplicationFacts($type, 2026)
            ->where('code', self::InspectionFeeCode)
            ->count() === 1;

        $new = $containsExactlyOneInspection(PermitApplicationType::New);
        $renewal = $containsExactlyOneInspection(PermitApplicationType::Renewal);

        return ['pass' => $new && $renewal, 'new' => $new, 'renewal' => $renewal];
    }

    /** @return list<array<string, mixed>> */
    private function roles(): array
    {
        $expected = $this->institution->roleDefinitions();

        return array_values(Role::query()->with('permissions')->whereIn('code', array_keys($expected))->orderBy('code')->get()
            ->map(fn (Role $role): array => [
                'code' => $role->code,
                'name' => $role->name,
                'required_permissions' => collect($expected[$role->code]['permissions'])->sort()->values()->all(),
                'assigned_permissions' => $role->permissions->pluck('code')->sort()->values()->all(),
                'is_administrative_envelope' => $role->code === UserRole::Admin->value,
                'is_municipal_office' => false,
            ])->all());
    }

    /** @return list<array<string, mixed>> */
    private function positions(): array
    {
        return array_values(InstitutionalPosition::query()->with('capabilityRole')->orderBy('code')->get()
            ->map(fn (InstitutionalPosition $position): array => [
                'code' => $position->code,
                'name' => $position->name,
                'capability_role_code' => $position->capabilityRole?->code,
                'authority_classification' => $position->authority_classification,
                'assignment_status' => $position->assignment_status,
                'production_commissioned' => (bool) data_get($position->metadata, 'production_commissioned', false),
            ])->all());
    }

    /**
     * @param  array<string, mixed>  $priceList
     * @return Collection<int, array<string, mixed>>
     */
    private function publishedCharges(array $priceList): Collection
    {
        $publishedCharges = new Collection;
        $services = $priceList['services'] ?? null;

        if (! is_iterable($services)) {
            return $publishedCharges;
        }

        foreach ($services as $service) {
            if (! is_array($service)) {
                continue;
            }

            $confirmedCharges = data_get($service, 'pricing.confirmed_charges');
            if (! is_iterable($confirmedCharges)) {
                continue;
            }

            foreach ($confirmedCharges as $charge) {
                if (is_array($charge)) {
                    $publishedCharges->push($charge);
                }
            }
        }

        return $publishedCharges;
    }

    /** @return array<string, int> */
    private function transactionCounts(): array
    {
        return [
            'business_owners' => BusinessOwner::query()->count(),
            'businesses' => Business::query()->count(),
            'permit_applications' => PermitApplication::query()->count(),
            'permit_application_lines' => PermitApplicationLine::query()->count(),
            'permit_clearances' => PermitClearance::query()->count(),
            'office_charge_contributions' => OfficeChargeContribution::query()->count(),
            'evaluations' => BusinessPermitEvaluation::query()->count(),
            'evaluation_versions' => BusinessPermitEvaluationVersion::query()->count(),
            'evaluation_responsibilities' => BusinessPermitEvaluationItem::query()->count(),
            'evaluation_revisions' => BusinessPermitEvaluationItemRevision::query()->count(),
            'treasury_counter_checks' => BusinessPermitEvaluationCounterCheck::query()->count(),
            'assessments' => Assessment::query()->count(),
            'assessment_lines' => AssessmentLine::query()->count(),
            'assessment_decisions' => AssessmentDecision::query()->count(),
            'payment_schedules' => PaymentSchedule::query()->count(),
            'payment_schedule_lines' => PaymentScheduleLine::query()->count(),
            'collections' => TreasuryCollection::query()->count(),
            'collection_allocations' => CollectionAllocation::query()->count(),
            'receipts' => Receipt::query()->count(),
            'provisional_permit_completions' => ProvisionalUatPermitCompletion::query()->count(),
        ];
    }

    /**
     * @param  Collection<int, FeeRule>  $acceptedInspectionRules
     * @param  Collection<int, array<string, mixed>>  $publishedCharges
     * @param  Collection<int, array<string, mixed>>  $syntheticPublishedCharges
     * @param  Collection<int, FeeRule>  $syntheticRules
     * @param  array{pass: bool, new: bool, renewal: bool}  $assessmentParity
     * @param  list<array<string, mixed>>  $roles
     * @param  list<array<string, mixed>>  $positions
     * @return list<string>
     */
    private function integrityIssues(
        Collection $acceptedInspectionRules,
        Collection $publishedCharges,
        Collection $syntheticPublishedCharges,
        Collection $syntheticRules,
        array $assessmentParity,
        array $roles,
        array $positions,
    ): array {
        $issues = [];
        $inspection = $acceptedInspectionRules->first();
        if ($acceptedInspectionRules->count() !== 1
            || ! $inspection instanceof FeeRule
            || $inspection->amount_cents !== 35_000
            || $inspection->currentReconciliation?->execution_status !== FeeRuleExecutionStatus::Executable
            || FeeRulePublicationSource::forRule($inspection) !== FeeRulePublicationSource::AcceptedMunicipalAuthority) {
            $issues[] = 'The accepted governed Business Inspection Fee is absent, duplicated, or non-executable.';
        }
        if ($publishedCharges->where('traceability.rule_code', self::InspectionFeeCode)->count() !== 1) {
            $issues[] = 'The public Price List does not contain exactly one unique governed Business Inspection Fee rule.';
        }
        if ($syntheticPublishedCharges->isNotEmpty() || $syntheticRules->isNotEmpty()) {
            $issues[] = 'Synthetic, provisional, scenario, mock, or evidence-only pricing leaked into installed pricing.';
        }
        if (! $assessmentParity['pass']) {
            $issues[] = 'Assessment does not select the installed governed Business Inspection Fee for new and renewal applications.';
        }
        if (count($roles) !== count($this->institution->roleDefinitions())) {
            $issues[] = 'One or more institutional capability roles are missing.';
        }
        foreach ($roles as $role) {
            if (array_diff($role['required_permissions'], $role['assigned_permissions']) !== []) {
                $issues[] = "Institutional role [{$role['code']}] is missing required capabilities.";
            }
        }
        if (Permission::query()->whereIn('code', array_column(UserPermission::cases(), 'value'))->count() !== count(UserPermission::cases())) {
            $issues[] = 'The canonical permission catalog is incomplete.';
        }
        if (count($positions) !== count($this->institution->positionDefinitions())) {
            $issues[] = 'One or more critical institutional positions are missing.';
        }

        return $issues;
    }

    /** @return array{status: string} */
    private function commissioningAdministrator(): array
    {
        $email = config('bpls_installation.commissioning_administrator.email');
        if (! is_string($email) || blank($email)) {
            return ['status' => 'external_link_required'];
        }

        $isLinked = User::query()->where('email', mb_strtolower(trim($email)))
            ->whereHas('role', fn ($query) => $query->where('code', UserRole::Admin->value))
            ->exists();

        return ['status' => $isLinked ? 'linked_password_reset_required' : 'configured_identity_missing'];
    }

    /** @param array<string, mixed> $payload */
    private function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode(
            $this->normalize($payload),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        ));
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }
}
