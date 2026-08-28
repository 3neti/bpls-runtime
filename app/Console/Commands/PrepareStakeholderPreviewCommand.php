<?php

namespace App\Console\Commands;

use App\Actions\CreateBillingGroup;
use App\Actions\CreateBillingGroupDraftRecord;
use App\Actions\CreateBillingGroupReconciliationEvidence;
use App\Enums\BillingGroupEvidenceType;
use App\Enums\BillingGroupFieldType;
use App\Enums\StakeholderPreviewPersona;
use App\Enums\UserPermission;
use App\LifecycleScenarios\ScenarioArtifactStore;
use App\Models\BillingGroup;
use App\Models\BillingGroupReconciliation;
use App\Models\BillingGroupRecord;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
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
#[Description('Prepare a deterministic synthetic stakeholder preview across the real citizen and staff application surfaces.')]
class PrepareStakeholderPreviewCommand extends Command
{
    public function handle(
        StakeholderPreviewSafety $previewSafety,
        CreateBillingGroup $createBillingGroup,
        CreateBillingGroupDraftRecord $createDraftRecord,
        CreateBillingGroupReconciliationEvidence $createReconciliationEvidence,
    ): int {
        try {
            $this->assertSafeEnvironment($previewSafety);
            $password = $this->runtimePassword();
            $runId = $this->runId();
            $accounts = $this->prepareAccounts($password);
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

    private function assertSafeEnvironment(StakeholderPreviewSafety $previewSafety): void
    {
        if (! $previewSafety->isEnabled()) {
            throw new RuntimeException('Stakeholder preview preparation refused because the canonical UAT safety gate is closed.');
        }
    }

    private function runtimePassword(): string
    {
        $password = config('stakeholder_preview.password');

        if (! is_string($password) || mb_strlen($password) < 16) {
            throw new RuntimeException('STAKEHOLDER_PREVIEW_PASSWORD must be supplied at runtime and contain at least 16 characters.');
        }

        return $password;
    }

    /** @return array<string, User> */
    private function prepareAccounts(string $password): array
    {
        return [
            'citizen' => $this->preparePersona(StakeholderPreviewPersona::Citizen, $password),
            'bplo' => $this->preparePersona(StakeholderPreviewPersona::Bplo, $password),
            'assessment_officer' => $this->preparePersona(StakeholderPreviewPersona::AssessmentOfficer, $password),
            'treasury' => $this->preparePersona(StakeholderPreviewPersona::Treasury, $password),
            'cashier' => $this->preparePersona(StakeholderPreviewPersona::Cashier, $password),
            'management' => $this->preparePersona(StakeholderPreviewPersona::Management, $password),
            'engineering' => $this->preparePersona(StakeholderPreviewPersona::Engineering, $password),
            'mpdo' => $this->preparePersona(StakeholderPreviewPersona::Mpdo, $password),
            'assessor' => $this->preparePersona(StakeholderPreviewPersona::Assessor, $password),
            'health' => $this->preparePersona(StakeholderPreviewPersona::Health, $password),
            'menro' => $this->preparePersona(StakeholderPreviewPersona::Menro, $password),
            'mayor_office' => $this->preparePersona(StakeholderPreviewPersona::MayorOffice, $password),
            'releasing' => $this->preparePersona(StakeholderPreviewPersona::Releasing, $password),
        ];
    }

    private function preparePersona(StakeholderPreviewPersona $persona, string $password): User
    {
        return $this->prepareUser(
            $persona->approvedEmail(),
            $persona->accountName(),
            $password,
            $this->previewRole($persona),
        );
    }

    private function previewRole(StakeholderPreviewPersona $persona): Role
    {
        $role = Role::query()->firstOrCreate(
            ['code' => $persona->roleCode()],
            [
                'name' => 'Preview '.$persona->label(),
                'description' => 'Synthetic preview-only effective-permission mapping; not accepted municipal job-role policy.',
            ],
        );
        $permissionIds = collect($persona->permissions())->map(function (UserPermission $permission): int {
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

    /** @param array<string, User> $accounts */
    private function configureScenario(array $accounts, string $password): void
    {
        config()->set('lifecycle_scenarios.actors.citizen_applicant.email', $accounts['citizen']->email);
        config()->set('lifecycle_scenarios.actors.primary_operator.email', $accounts['bplo']->email);
        config()->set('lifecycle_scenarios.actors.assessment_preparer.email', $accounts['assessment_officer']->email);
        config()->set('lifecycle_scenarios.actors.assessment_approver.email', $accounts['treasury']->email);
        config()->set('lifecycle_scenarios.actors.preview_cashier.email', $accounts['cashier']->email);
        config()->set('lifecycle_scenarios.actors.sample_recipient.email', $accounts['management']->email);
        config()->set('lifecycle_scenarios.actors.preview_engineering.email', $accounts['engineering']->email);
        config()->set('lifecycle_scenarios.actors.preview_mpdo.email', $accounts['mpdo']->email);
        config()->set('lifecycle_scenarios.actors.preview_assessor.email', $accounts['assessor']->email);
        config()->set('lifecycle_scenarios.actors.preview_health.email', $accounts['health']->email);
        config()->set('lifecycle_scenarios.actors.preview_menro.email', $accounts['menro']->email);
        config()->set('lifecycle_scenarios.actors.preview_mayor_office.email', $accounts['mayor_office']->email);
        config()->set('lifecycle_scenarios.actors.preview_releasing.email', $accounts['releasing']->email);

        $environment = [
            'LIFECYCLE_BROWSER_EMAIL' => $accounts['citizen']->email,
            'LIFECYCLE_BROWSER_PASSWORD' => $password,
            'LIFECYCLE_BROWSER_OPERATOR_EMAIL' => $accounts['bplo']->email,
            'LIFECYCLE_BROWSER_OPERATOR_PASSWORD' => $password,
            'LIFECYCLE_BROWSER_BPLO_EMAIL' => $accounts['bplo']->email,
            'LIFECYCLE_BROWSER_BPLO_PASSWORD' => $password,
            'LIFECYCLE_BROWSER_TREASURY_EMAIL' => $accounts['treasury']->email,
            'LIFECYCLE_BROWSER_TREASURY_PASSWORD' => $password,
            'LIFECYCLE_ASSESSMENT_PREPARER_EMAIL' => $accounts['assessment_officer']->email,
            'LIFECYCLE_ASSESSMENT_APPROVER_EMAIL' => $accounts['treasury']->email,
            'LIFECYCLE_PREVIEW_CASHIER_EMAIL' => $accounts['cashier']->email,
            'LIFECYCLE_PREVIEW_ENGINEERING_EMAIL' => $accounts['engineering']->email,
            'LIFECYCLE_PREVIEW_MPDO_EMAIL' => $accounts['mpdo']->email,
            'LIFECYCLE_PREVIEW_ASSESSOR_EMAIL' => $accounts['assessor']->email,
            'LIFECYCLE_PREVIEW_HEALTH_EMAIL' => $accounts['health']->email,
            'LIFECYCLE_PREVIEW_MENRO_EMAIL' => $accounts['menro']->email,
            'LIFECYCLE_PREVIEW_MAYOR_OFFICE_EMAIL' => $accounts['mayor_office']->email,
            'LIFECYCLE_PREVIEW_RELEASING_EMAIL' => $accounts['releasing']->email,
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
     * @param  array<string, User>  $accounts
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
            'data_classification' => 'synthetic_uat_only',
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
            'provisional_semantics' => [
                'classification' => 'provisional_uat',
                'concerned_office_charges' => true,
                'full_consolidated_payment_is_scenario_choice' => true,
                'partial_payment_capability_disabled' => false,
                'mayor_go_no_go' => true,
                'synthetic_signature_only' => true,
                'preview_permit_number_only' => true,
                'preview_release_only' => true,
                'production_authority' => false,
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

    private function runId(): string
    {
        $value = $this->option('run-id');
        $runId = is_string($value) && $value !== ''
            ? $value
            : 'stakeholder-preview-weekend-'.now()->format('Ymd-His');

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runId) !== 1) {
            throw new RuntimeException('The stakeholder preview run ID must be a stable filesystem-safe reference.');
        }

        return $runId;
    }
}
