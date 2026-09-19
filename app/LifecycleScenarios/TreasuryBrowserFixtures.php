<?php

namespace App\LifecycleScenarios;

use App\Actions\AuthorizeRoutedOfficeActor;
use App\Actions\BuildBploRoutingTask;
use App\Actions\BuildMunicipalWorkInbox;
use App\Actions\ConfirmOfficePaymentOrder;
use App\Actions\CreateCitizenPermitApplicationDraft;
use App\Actions\InitializeBusinessPermitEvaluation;
use App\Actions\ProvisionLifecycleLaboratoryActors;
use App\Actions\RecordBploRoutingDetermination;
use App\Actions\SubmitCitizenPermitApplication;
use App\Assessment\ConcernedOfficeFeeApplicability;
use App\Assessment\ProvisionalTreasuryEnterpriseSchedule;
use App\Enums\UserPermission;
use App\Models\FeeRule;
use App\Models\InstitutionalPositionAssignment;
use App\Models\PaperlessPaymentOrder;
use App\Models\PermitApplication;
use App\Models\Role;
use App\Models\SignatureEvidence;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TreasuryBrowserFixtures
{
    public const Keys = ['T2026-NO-LOB', 'T2026-ADMITTED-ENTERPRISE'];

    private const OfficeItems = [
        'assessor' => [['IPIL-LEGACY-E5B97AA20294C7AA', 10000]],
        'engineering' => [['FEE-C2E404D2D1B97545', 15000]],
        'health' => [['IPIL-LEGACY-99C7F1CE5E8189C8', 10000], ['IPIL-LEGACY-04845A0127A00E12', 20000]],
        'menro' => [['IPIL-LEGACY-98CDCAD9D28055FB', 250000]],
    ];

    /** @return array<string, mixed> */
    public function prepare(string $key): array
    {
        $this->assertSafe();
        if (! in_array($key, self::Keys, true)) {
            throw new RuntimeException('Unsupported Treasury fixture; no records created.');
        }
        $definitions = app(ProvisionLifecycleLaboratoryActors::class)->definitions();
        $actors = [];
        foreach (['intake', 'assessment_officer', 'treasury', 'assessor', 'engineering', 'health', 'menro'] as $actorKey) {
            $actors[$actorKey] = User::query()->where('email', $definitions[$actorKey]['email'])->sole();
        }

        return DB::transaction(function () use ($key, $actors): array {
            $existing = PermitApplication::query()->where('metadata->treasury_browser_fixture->key', $key)->lockForUpdate()->get();
            if ($existing->count() > 1) {
                throw new RuntimeException('Duplicate fixture identity; refusing to choose a record.');
            }
            if ($existing->count() === 1) {
                return $this->manifest($existing->sole(), $actors['treasury']);
            }
            if (User::query()->where('email', $this->email($key))->exists()) {
                throw new RuntimeException('Fixture Citizen already exists without its Application; inspect before retrying.');
            }
            $citizen = User::factory()->create([
                'name' => 'Synthetic Treasury Fixture Citizen',
                'email' => $this->email($key),
                'password' => Str::random(48),
                'business_owner_id' => null,
            ]);
            $citizen->assignRole(Role::query()->where('code', 'citizen')->sole());
            $application = app(CreateCitizenPermitApplicationDraft::class)->handle([
                'application_year' => 2026, 'type' => 'new',
                'owner_name' => $citizen->name, 'business_name' => 'SYNTHETIC TREASURY '.$key,
                'business_activity_description' => 'Synthetic Treasury browser fixture only. Retail sale of fresh fish.',
                'business_address' => 'Synthetic market address, Don Andres, Ipil',
                'barangay' => 'Don Andres', 'business_barangay_psgc_code' => '0908305006',
                'ownership_type' => 'sole-proprietorship', 'business_area_square_meters' => '12.00',
                'male_employee_count' => 1, 'female_employee_count' => 0,
                'total_employee_count' => 1, 'employees_residing_in_lgu' => 1,
                'undertaking_accepted' => true, 'applicant_printed_name' => $citizen->name,
                'position_title' => 'Synthetic fixture only',
            ], $citizen);
            $metadata = $application->metadata;
            $metadata['treasury_browser_fixture'] = [
                'key' => $key, 'version' => 1, 'semantic_classification' => 'synthetic_only',
                'production_liability' => false, 'production_policy_authority' => false,
                'boundary' => 'before_treasury_classification',
                'test_purpose' => $key === 'T2026-NO-LOB' ? 'observe_initial_empty_selection' : 'transient_enterprise_selection_without_confirmation',
                'signature_provenance' => 'generated synthetic test images; not actual officer signatures',
            ];
            $application->update(['metadata' => $metadata]);
            $this->preflight($application, $actors);
            $application = app(SubmitCitizenPermitApplication::class)->handle($application, $citizen, true, UploadedFile::fake()->image('synthetic-citizen-test-mark.png'));
            $routing = app(RecordBploRoutingDetermination::class)->handle($application, $actors['intake'], 'Dedicated local synthetic Treasury acceptance fixture.',
                array_values(collect(['assessor', 'engineering', 'health', 'menro'])->map(fn (string $office): array => [
                    'office_code' => $office, 'office_label' => $office,
                    'situational_reason' => 'Synthetic fixture routing, not an actual municipal determination.',
                    'required_work' => 'Prepare office Payment Order.',
                ])->all()));
            app(InitializeBusinessPermitEvaluation::class)->handle($application, $actors['assessment_officer']);
            foreach ($routing->works as $work) {
                $editor = app(BuildBploRoutingTask::class)->handle($application->fresh(), $actors[$work->office_code])->financial_editor;
                $items = [];
                foreach (self::OfficeItems[$work->office_code] as [$code, $amount]) {
                    $fee = collect($this->rows(data_get($editor, 'office_fee_options.'.$work->office_code)))->where('code', $code)->sole();
                    $items[] = ['fee_rule_id' => $fee['id'], 'amount_cents' => $amount];
                }
                app(ConfirmOfficePaymentOrder::class)->handle($work, $items,
                    $actors[$work->office_code], UploadedFile::fake()->image('synthetic-'.$work->office_code.'-test-mark.png'));
            }

            $application->refresh();
            $metadata = $application->metadata;
            $metadata['treasury_browser_fixture']['sealed_state_sha256'] = $this->stateHash($application);
            $application->update(['metadata' => $metadata]);

            return $this->manifest($application->fresh(), $actors['treasury']);
        });
    }

    private function assertSafe(): void
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $connection = config('database.connections.'.config('database.default'));
        $disk = config('filesystems.disks.'.config('filesystems.signature_evidence_disk'));
        if (! app()->environment(['local', 'testing']) || ! is_string($host) || ! str_ends_with($host, '.test')
            || config('stakeholder_preview.mode') !== true
            || config('stakeholder_preview.production_migration_enabled') !== false
            || config('stakeholder_preview.production_integrations') !== 'disabled'
            || config('stakeholder_preview.pii_mode') !== 'synthetic_only'
            || data_get($disk, 'driver') !== 'local'
            || (data_get($connection, 'driver') !== 'sqlite' && ! in_array(data_get($connection, 'host'), ['127.0.0.1', 'localhost', '::1'], true))
            || filled(data_get($connection, 'url'))) {
            throw new RuntimeException('Treasury fixtures require a synthetic-only local .test host, local database/storage and disabled production integrations.');
        }
    }

    /** @param array<string, User> $actors */
    private function preflight(PermitApplication $application, array $actors): void
    {
        foreach (['intake' => UserPermission::DetermineBploRouting, 'assessment_officer' => UserPermission::AssessPermitApplications, 'treasury' => UserPermission::CorrectEvaluationLinesOfBusiness] as $key => $permission) {
            if (! $actors[$key]->can($permission->value)) {
                throw new RuntimeException('Existing fixture actor lacks its required permission.');
            }
        }
        if (! InstitutionalPositionAssignment::query()->where('user_id', $actors['treasury']->id)->where('status', 'active')->whereNull('ended_at')->whereHas('position.capabilityRole', fn ($query) => $query->where('code', 'treasury'))->exists()) {
            throw new RuntimeException('Existing Treasury actor has no active Inbox position.');
        }
        $mayor = FeeRule::query()->where('code', ProvisionalTreasuryEnterpriseSchedule::FeeCode)->where('is_active', true)->sole();
        if (app(ProvisionalTreasuryEnterpriseSchedule::class)->forApplication($application, $mayor) === null) {
            throw new RuntimeException('Existing local provisional schedule is not admitted; fixture builder will not enable it.');
        }
        foreach (self::OfficeItems as $office => $items) {
            app(AuthorizeRoutedOfficeActor::class)->handle($application, $office, $actors[$office]);
            if (! $actors[$office]->can(UserPermission::ContributeBusinessPermitEvaluations->value)) {
                throw new RuntimeException('Existing office actor lacks Payment Order permission.');
            }
            foreach ($items as [$code, $amount]) {
                $rule = FeeRule::query()->where('code', $code)->where('is_active', true)->sole();
                if (! app(ConcernedOfficeFeeApplicability::class)->matches($rule, $application, $office)) {
                    throw new RuntimeException('Fixture fee is not assigned to the expected office.');
                }
            }
        }
    }

    private function email(string $key): string
    {
        return strtolower($key).'@treasury-fixture.example.test';
    }

    /** @return list<array<string, mixed>> */
    private function rows(mixed $items): array
    {
        if (! is_array($items)) {
            throw new RuntimeException('Expected a canonical financial option list.');
        }
        $rows = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new RuntimeException('Expected a canonical financial option record.');
            }
            $rows[] = $item;
        }

        return $rows;
    }

    private function stateHash(PermitApplication $application): string
    {
        $metadata = $application->metadata;
        unset($metadata['treasury_browser_fixture']['sealed_state_sha256']);
        $identity = $application->getRawOriginal();
        unset($identity['metadata'], $identity['updated_at']);
        $declaration = $application->declaration()->sole();
        $orders = $application->paperlessPaymentOrders()->with(['lines' => fn ($query) => $query->orderBy('id')])->orderBy('id')->get();
        $signatures = SignatureEvidence::query()
            ->where(fn ($query) => $query->where('signable_type', $declaration->getMorphClass())->where('signable_id', $declaration->id))
            ->orWhere(fn ($query) => $query->where('signable_type', (new PaperlessPaymentOrder)->getMorphClass())->whereIn('signable_id', $orders->modelKeys()))
            ->orderBy('id')->get();
        if ($signatures->count() !== 5) {
            throw new RuntimeException('Fixture changed: exactly five synthetic signature records are required.');
        }
        $facts = [
            'application_identity' => $identity,
            'metadata' => $metadata,
            'status' => $application->status->value,
            'type' => $application->type->value,
            'year' => $application->application_year,
            'business' => $application->business()->sole()->getRawOriginal(),
            'declaration' => $declaration->getRawOriginal(),
            'routing' => $application->bploRoutingDetermination()->with(['works' => fn ($query) => $query->orderBy('id')])->orderBy('id')->get()->toArray(),
            'orders' => $orders->toArray(),
            'signatures' => $signatures->map->getRawOriginal()->all(),
        ];

        return hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function manifest(PermitApplication $application, User $treasury): array
    {
        $marker = data_get($application->metadata, 'treasury_browser_fixture');
        if (data_get($marker, 'version') !== 1 || data_get($marker, 'production_liability') !== false
            || data_get($marker, 'semantic_classification') !== 'synthetic_only'
            || data_get($marker, 'production_policy_authority') !== false
            || data_get($marker, 'boundary') !== 'before_treasury_classification'
            || ! in_array(data_get($marker, 'key'), self::Keys, true)
            || data_get($marker, 'sealed_state_sha256') !== $this->stateHash($application)
            || $application->application_year !== 2026 || $application->submitted_at === null
            || $application->lines()->exists() || $application->treasuryLineOfBusinessAssignments()->exists()
            || $application->assessments()->exists() || $application->paymentSchedules()->exists()
            || $application->paperlessPaymentOrders()->where('status', 'issued')->whereNull('superseded_at')->count() !== 4) {
            throw new RuntimeException('Fixture changed or crossed its Treasury boundary; refusing to overwrite or repair it.');
        }
        $editor = app(BuildBploRoutingTask::class)->handle($application, $treasury)->financial_editor;
        $lob = collect($this->rows($editor['line_of_business_options'] ?? null))->where('code', 'LOB-3A9A93CA46967768')->sole();
        $mayor = collect($this->rows($lob['default_items'] ?? null))->where('code', ProvisionalTreasuryEnterpriseSchedule::FeeCode)->sole();
        if (! is_array($mayor['enterprise_schedule'] ?? null)) {
            throw new RuntimeException('Existing local provisional schedule is not admitted; fixture builder will not enable it.');
        }
        $inbox = app(BuildMunicipalWorkInbox::class)->handle($treasury)['items']->where('task_type', 'treasury_classification')->firstWhere('application.id', $application->id);
        if ($inbox === null) {
            throw new RuntimeException('Fixture is not discoverable in the existing Treasury actor Inbox.');
        }
        $facts = ['fixture' => $marker, 'application_id' => $application->id,
            'tracking_reference' => $application->tracking_reference,
            'declaration_sha256' => $application->declaration->snapshot_hash,
            'payment_order_ids' => $application->paperlessPaymentOrders()->orderBy('id')->pluck('id')->all(),
            'payment_order_total_cents' => $application->paperlessPaymentOrders()->sum('total_amount_cents'),
            'line_of_business_id' => $lob['id'], 'line_of_business_code' => $lob['code'],
            'mayor_fee_rule_id' => $mayor['fee_rule_id'], 'mayor_fee_code' => $mayor['code'],
            'enterprise_schedule' => $mayor['enterprise_schedule'],
            'url' => route('staff.permit-applications.evaluation.show', $application),
            'treasury_assignments' => 0, 'assessments' => 0, 'payment_schedules' => 0,
        ];

        return [...$facts, 'fingerprint' => hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR))];
    }
}
