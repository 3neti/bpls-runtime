<?php

namespace App\Console\Commands;

use App\Actions\CreateBillingGroup;
use App\Actions\CreateBillingGroupDraftRecord;
use App\Actions\CreateBillingGroupReconciliationEvidence;
use App\Actions\EnsureCitizenRole;
use App\Enums\BillingGroupEvidenceType;
use App\Enums\BillingGroupFieldType;
use App\Enums\UserPermission;
use App\LifecycleScenarios\ScenarioArtifactStore;
use App\Models\BillingGroup;
use App\Models\BillingGroupReconciliation;
use App\Models\BillingGroupRecord;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Throwable;

#[Signature('lifecycle:prepare-stakeholder-preview
    {--run-id= : Stable preview run reference}
    {--phase=all : prepare, browser, audit, or all}
    {--base-url=http://bpls-runtime.test : Browser base URL}')]
#[Description('Prepare a local deterministic stakeholder preview across the real citizen and staff application surfaces.')]
class PrepareStakeholderPreviewCommand extends Command
{
    /** @var list<UserPermission> */
    private const array BploPermissions = [
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::CreatePermitApplications,
        UserPermission::AssessPermitApplications,
        UserPermission::UpdatePermitApplicationStatus,
        UserPermission::CompletePermitClearances,
        UserPermission::ViewFeeRules,
        UserPermission::ViewPaymentSchedules,
        UserPermission::PreparePaymentSchedules,
    ];

    /** @var list<UserPermission> */
    private const array TreasuryPermissions = [
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::ViewPaymentSchedules,
        UserPermission::ViewCollections,
        UserPermission::RecordCollections,
        UserPermission::ViewReceipts,
        UserPermission::IssueReceipts,
        UserPermission::ViewReports,
    ];

    /** @var list<UserPermission> */
    private const array ManagementPermissions = [
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
        UserPermission::CreatePermitApplications,
        UserPermission::AssessPermitApplications,
        UserPermission::UpdatePermitApplicationStatus,
        UserPermission::CompletePermitClearances,
        UserPermission::ViewPaymentSchedules,
        UserPermission::PreparePaymentSchedules,
        UserPermission::ViewCollections,
        UserPermission::RecordCollections,
        UserPermission::ViewReceipts,
        UserPermission::IssueReceipts,
        UserPermission::VoidReceipts,
        UserPermission::ViewReports,
        UserPermission::ViewUsers,
        UserPermission::ViewRoles,
        UserPermission::ViewMunicipalityConfiguration,
        UserPermission::ViewFeeRules,
        UserPermission::ViewBillingGroups,
        UserPermission::ViewBillingGroupRecords,
        UserPermission::ManageStoryboards,
    ];

    public function handle(
        EnsureCitizenRole $ensureCitizenRole,
        CreateBillingGroup $createBillingGroup,
        CreateBillingGroupDraftRecord $createDraftRecord,
        CreateBillingGroupReconciliationEvidence $createReconciliationEvidence,
    ): int {
        try {
            $this->assertSafeEnvironment();
            $password = $this->runtimePassword();
            $runId = $this->runId();
            $accounts = $this->prepareAccounts($password, $ensureCitizenRole);
            $this->configureScenario($accounts, $password);
            $phase = (string) $this->option('phase');

            if (in_array($phase, ['prepare', 'all'], true)) {
                $this->runScenarioPhase('prepare', $runId);
                $this->augmentComposition(
                    $runId,
                    $accounts,
                    $createBillingGroup,
                    $createDraftRecord,
                    $createReconciliationEvidence,
                );
            }

            if (in_array($phase, ['browser', 'all'], true)) {
                $this->runScenarioPhase('browser', $runId);
            }

            if (in_array($phase, ['audit', 'all'], true)) {
                $this->runScenarioPhase('audit', $runId);
            }

            $this->newLine();
            $this->line('Deterministic stakeholder preview prepared from synthetic runtime credentials.');
            foreach ($accounts as $key => $user) {
                $this->line(str($key)->headline()->toString().': '.$user->email);
            }
            $this->line('Password: supplied by STAKEHOLDER_PREVIEW_PASSWORD (not displayed)');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function assertSafeEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Stakeholder preview preparation is allowed only in local or testing.');
        }
    }

    private function runtimePassword(): string
    {
        $password = getenv('STAKEHOLDER_PREVIEW_PASSWORD');

        if (! is_string($password) || mb_strlen($password) < 16) {
            throw new RuntimeException('STAKEHOLDER_PREVIEW_PASSWORD must be supplied at runtime and contain at least 16 characters.');
        }

        return $password;
    }

    /** @return array{citizen: User, bplo: User, treasury: User, management: User} */
    private function prepareAccounts(string $password, EnsureCitizenRole $ensureCitizenRole): array
    {
        return [
            'citizen' => $this->prepareUser(
                $this->runtimeEmail('STAKEHOLDER_PREVIEW_CITIZEN_EMAIL', 'preview.citizen@example.test'),
                'Preview Citizen',
                $password,
                $ensureCitizenRole->handle(),
            ),
            'bplo' => $this->prepareUser(
                $this->runtimeEmail('STAKEHOLDER_PREVIEW_BPLO_EMAIL', 'preview.bplo@example.test'),
                'Preview BPLO Operator',
                $password,
                $this->previewRole('preview_bplo', 'Preview BPLO', self::BploPermissions),
            ),
            'treasury' => $this->prepareUser(
                $this->runtimeEmail('STAKEHOLDER_PREVIEW_TREASURY_EMAIL', 'preview.treasury@example.test'),
                'Preview Treasury Operator',
                $password,
                $this->previewRole('preview_treasury', 'Preview Treasury', self::TreasuryPermissions),
            ),
            'management' => $this->prepareUser(
                $this->runtimeEmail('STAKEHOLDER_PREVIEW_MANAGEMENT_EMAIL', 'preview.management@example.test'),
                'Preview Municipal Management',
                $password,
                $this->previewRole('preview_management', 'Preview Municipal Management', self::ManagementPermissions),
            ),
        ];
    }

    /** @param list<UserPermission> $permissions */
    private function previewRole(string $code, string $name, array $permissions): Role
    {
        $role = Role::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'description' => 'Synthetic preview-only effective-permission mapping; not accepted municipal job-role policy.',
            ],
        );
        $permissionIds = collect($permissions)->map(function (UserPermission $permission): int {
            return Permission::query()->firstOrCreate(
                ['code' => $permission->value],
                [
                    'name' => str($permission->value)->replace(['.', '_'], ' ')->title()->toString(),
                    'description' => null,
                ],
            )->id;
        });
        $role->permissions()->sync($permissionIds->all());

        return $role;
    }

    private function prepareUser(string $email, string $name, string $password, Role $role): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'role_id' => $role->id,
            'password' => Hash::make($password),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return $user->refresh();
    }

    /** @param array{citizen: User, bplo: User, treasury: User, management: User} $accounts */
    private function configureScenario(array $accounts, string $password): void
    {
        config()->set('lifecycle_scenarios.actors.citizen_applicant.email', $accounts['citizen']->email);
        config()->set('lifecycle_scenarios.actors.primary_operator.email', $accounts['management']->email);
        config()->set('lifecycle_scenarios.actors.sample_recipient.email', $accounts['management']->email);

        $environment = [
            'LIFECYCLE_BROWSER_EMAIL' => $accounts['citizen']->email,
            'LIFECYCLE_BROWSER_PASSWORD' => $password,
            'LIFECYCLE_BROWSER_OPERATOR_EMAIL' => $accounts['management']->email,
            'LIFECYCLE_BROWSER_OPERATOR_PASSWORD' => $password,
            'LIFECYCLE_BROWSER_BPLO_EMAIL' => $accounts['bplo']->email,
            'LIFECYCLE_BROWSER_BPLO_PASSWORD' => $password,
            'LIFECYCLE_BROWSER_TREASURY_EMAIL' => $accounts['treasury']->email,
            'LIFECYCLE_BROWSER_TREASURY_PASSWORD' => $password,
        ];

        foreach ($environment as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    private function runScenarioPhase(string $phase, string $runId): void
    {
        $exitCode = $this->call('lifecycle:scenario', [
            'scenario' => 'stakeholder_preview_cycle_1',
            '--run-id' => $runId,
            '--phase' => $phase,
            '--base-url' => (string) $this->option('base-url'),
        ]);

        if ($exitCode !== self::SUCCESS) {
            throw new RuntimeException("Stakeholder preview {$phase} phase failed.");
        }
    }

    /**
     * @param  array{citizen: User, bplo: User, treasury: User, management: User}  $accounts
     */
    private function augmentComposition(
        string $runId,
        array $accounts,
        CreateBillingGroup $createBillingGroup,
        CreateBillingGroupDraftRecord $createDraftRecord,
        CreateBillingGroupReconciliationEvidence $createReconciliationEvidence,
    ): void {
        $billingGroup = BillingGroup::query()->where('metadata->scenario_run_id', $runId)->first();
        if (! $billingGroup instanceof BillingGroup) {
            $billingGroup = $createBillingGroup->handle([
                'name' => 'Preview provisional records '.str($runId)->limit(70, '')->toString(),
                'description' => 'Synthetic preview evidence for a deliberately non-executable billing-group boundary.',
                'fields' => [[
                    'key' => 'subject_name',
                    'name' => 'Subject Name',
                    'field_type' => BillingGroupFieldType::Text->value,
                    'is_required' => true,
                    'is_unique' => false,
                    'options' => [],
                    'placeholder' => 'Recorded for later municipal review',
                    'default_value' => null,
                ]],
            ], ['scenario_run_id' => $runId, 'preview_data' => 'synthetic']);
        }

        $record = BillingGroupRecord::query()->where('source_snapshot->scenario_run_id', $runId)->first();
        if (! $record instanceof BillingGroupRecord) {
            $record = $createDraftRecord->handle($billingGroup, $accounts['management'], [
                'description' => 'Synthetic draft; no liability, collection, or receipt effect.',
                'record_date' => now()->toDateString(),
                'payor_name' => 'Preview Synthetic Payor',
                'field_values' => [],
            ], ['scenario_run_id' => $runId, 'preview_data' => 'synthetic']);
        }

        $reconciliation = BillingGroupReconciliation::query()->where('metadata->scenario_run_id', $runId)->first();
        if (! $reconciliation instanceof BillingGroupReconciliation) {
            $reconciliation = $createReconciliationEvidence->handle($billingGroup, $accounts['management'], [
                'evidence_type' => BillingGroupEvidenceType::LegacyConfiguration->value,
                'evidence_reference' => 'Synthetic preview characterization '.$runId,
                'source_excerpt' => 'Legacy configuration shape retained as evidence only.',
                'operational_interpretation' => 'Demonstrate the refusal boundary without accepting fiscal policy.',
                'unresolved_questions' => [
                    'Which municipal authority may accept this billing group and its financial treatment?',
                ],
            ], ['scenario_run_id' => $runId, 'preview_data' => 'synthetic']);
        }

        $store = new ScenarioArtifactStore('stakeholder_preview_cycle_1', $runId);
        $manifest = $store->readJson('manifest.json') ?? throw new RuntimeException('Stakeholder preview manifest is missing after preparation.');
        $manifest['preview'] = [
            'data_classification' => 'synthetic_local_demo_only',
            'production_migration_executed' => false,
            'role_mapping_status' => 'preview_effective_permissions_not_municipal_policy',
            'credential_delivery' => [
                'password_source' => 'STAKEHOLDER_PREVIEW_PASSWORD',
                'password_embedded_in_git' => false,
            ],
            'accounts' => collect($accounts)->map(fn (User $user): array => [
                'name' => $user->name,
                'email' => $user->email,
                'role_code' => $user->role?->code,
            ])->all(),
            'urls' => [
                'report_catalog' => route('staff.reports.index', absolute: false),
                'users' => route('staff.users.index', absolute: false),
                'roles' => route('staff.roles.index', absolute: false),
                'municipality' => route('staff.municipality-configuration.index', absolute: false),
                'fee_rules' => route('staff.fee-rules.index', absolute: false),
                'billing_groups' => route('staff.billing-groups.index', absolute: false),
                'billing_group_detail' => route('staff.billing-groups.show', $billingGroup, false),
                'billing_group_abstract' => route('staff.reports.billing-groups.abstract.index', $billingGroup, false),
            ],
            'billing_group' => [
                'id' => $billingGroup->id,
                'record_id' => $record->id,
                'reconciliation_id' => $reconciliation->id,
                'acceptance_status' => $billingGroup->acceptance_status->value,
                'record_status' => $record->status->value,
                'execution_status' => $reconciliation->execution_status,
                'financial_effect' => 'none',
            ],
        ];
        $store->putJson('manifest.json', $manifest);
    }

    private function runtimeEmail(string $key, string $default): string
    {
        $value = getenv($key);
        $email = is_string($value) && $value !== '' ? $value : $default;

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException("{$key} must be a valid email address.");
        }

        return $email;
    }

    private function runId(): string
    {
        $value = $this->option('run-id');
        $runId = is_string($value) && $value !== ''
            ? $value
            : 'stakeholder-preview-cycle4-'.now()->format('Ymd-His');

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runId) !== 1) {
            throw new RuntimeException('The stakeholder preview run ID must be a stable filesystem-safe reference.');
        }

        return $runId;
    }
}
