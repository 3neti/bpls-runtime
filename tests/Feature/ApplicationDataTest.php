<?php

use App\Actions\ExecutePersistedLifecycleScenario;
use App\Actions\IssueManualCollectionReceipt;
use App\Actions\RecordPaymentScheduleCollection;
use App\Data\Application\ApplicationDataResolver;
use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\Models\LifecycleScenarioSpecimen;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    configureApplicationDataPreview();
    Artisan::call('bpls:install');
});

afterEach(function () {
    config()->set('stakeholder_preview.mode', false);
});

test('ApplicationData V1 contains typed canonical facts without Eloquent models and varies only actor context', function () {
    app(ExecutePersistedLifecycleScenario::class)->handle(NewApplicationHappyPathDefinition::Id);
    $specimen = LifecycleScenarioSpecimen::query()->where('scenario_id', NewApplicationHappyPathDefinition::Id)->sole();
    $application = $specimen->permitApplication;
    $citizen = User::query()->with('role.permissions')->where('email', 'scenario-citizen@example.test')->sole();
    $health = User::query()->with('role.permissions')->where('email', 'scenario-01-health@example.test')->sole();
    $resolver = app(ApplicationDataResolver::class);

    $citizenData = $resolver->resolve($application, $citizen)->toArray();
    $healthData = $resolver->resolve($application, $health)->toArray();
    $assertNoModels = function (mixed $value) use (&$assertNoModels): void {
        expect($value)->not->toBeInstanceOf(Model::class);
        if (is_array($value)) {
            foreach ($value as $nested) {
                $assertNoModels($nested);
            }
        }
    };
    $assertNoModels($citizenData);
    $stableWorkNotes = fn (array $notes): array => collect($notes)
        ->map(fn (array $note): array => Arr::except($note, ['actionable', 'action_label', 'action_url']))
        ->all();
    $citizenPaymentNote = collect($citizenData['actor_context']['work_notes'])->firstWhere('id', 'applicant_payment');
    $healthPaymentNote = collect($healthData['actor_context']['work_notes'])->firstWhere('id', 'applicant_payment');
    $noteCopy = collect($citizenData['actor_context']['work_notes'])
        ->flatMap(fn (array $note): array => Arr::only($note, ['actor_label', 'instruction', 'state_label', 'blocking_reason']))
        ->filter()
        ->implode(' ');

    expect($citizenData['schema_version'])->toBe('bpls.application-data.v1')
        ->and($citizenData['identity'])->toBe($healthData['identity'])
        ->and($citizenData['declaration'])->toBe($healthData['declaration'])
        ->and($citizenData['financial'])->toBe($healthData['financial'])
        ->and($citizenData['actor_context'])->not->toBe($healthData['actor_context'])
        ->and($citizenData['actor_context']['current_tasks'])->toHaveCount(1)
        ->and($citizenData['actor_context']['current_tasks'][0]['key'])->toBe('pay_balance')
        ->and($healthData['actor_context']['current_tasks'])->toBe([])
        ->and($stableWorkNotes($citizenData['actor_context']['work_notes']))->toBe($stableWorkNotes($healthData['actor_context']['work_notes']))
        ->and($citizenData['actor_context']['work_notes'])->toHaveCount(count($healthData['actor_context']['work_notes']))
        ->and($citizenPaymentNote['actionable'])->toBeTrue()
        ->and($citizenPaymentNote['action_url'])->not->toBeNull()
        ->and($healthPaymentNote['actionable'])->toBeFalse()
        ->and($healthPaymentNote['action_url'])->toBeNull()
        ->and($noteCopy)->not->toMatch('/\b(your|yours|my|mine|their|theirs|his|hers|its)\b/i')
        ->and($citizenData['tabs'])->toBe([
            ['key' => 'application', 'label' => 'Application'],
            ['key' => 'processing', 'label' => 'Processing'],
            ['key' => 'assessment', 'label' => 'Assessment'],
            ['key' => 'payment', 'label' => 'Payment'],
            ['key' => 'permit', 'label' => 'Permit'],
        ]);
});

test('frozen Page 1 and frozen PriceReport survive ApplicationData reconstruction while Page 2 reflects canonical processing', function () {
    app(ExecutePersistedLifecycleScenario::class)->handle(NewApplicationHappyPathDefinition::Id);
    $application = PermitApplication::query()->sole();
    $assessment = $application->assessments()->whereNull('superseded_at')->sole();
    $priceReport = $assessment->price_report_snapshot;
    $snapshotHash = $application->declaration()->sole()->snapshot_hash;

    $data = app(ApplicationDataResolver::class)->resolve($application)->toArray();

    expect($data['declaration']['page'])->toBe('page_1')
        ->and($data['declaration']['state'])->toBe('frozen')
        ->and($data['declaration']['snapshot_hash'])->toBe($snapshotHash)
        ->and($data['routing']['page'])->toBe('page_2')
        ->and($data['routing']['status'])->toBe('determined')
        ->and($data['offices'])->not->toBeEmpty()
        ->and($data['financial']['price_report'])->toBe($priceReport)
        ->and($assessment->refresh()->price_report_snapshot)->toBe($priceReport);
});

test('Official Receipt projection requires canonical Receipt truth and permit validity remains false without legal authority', function () {
    app(ExecutePersistedLifecycleScenario::class)->handle(NewApplicationHappyPathDefinition::Id);
    $application = PermitApplication::query()->sole();
    $schedule = $application->paymentSchedules()->sole();
    $collector = User::query()->with('role.permissions')->where('email', 'scenario-01-treasury-counter-check@example.test')->sole();
    $collection = app(RecordPaymentScheduleCollection::class)->handle($schedule, [
        'amount_cents' => $schedule->total_amount_cents,
        'method' => 'cash',
        'payer_name' => 'Synthetic Scenario Citizen',
    ], $collector);

    $beforeReceipt = app(ApplicationDataResolver::class)->resolve($application->fresh())->toArray();
    expect($beforeReceipt['payment']['collections'])->toHaveCount(1)
        ->and($beforeReceipt['official_receipts'])->toBe([])
        ->and($beforeReceipt['permit']['official_receipt_bound'])->toBeFalse()
        ->and($beforeReceipt['permit']['valid'])->toBeFalse();

    app(IssueManualCollectionReceipt::class)->handle($collection, [
        'receipt_number' => 'SYNTHETIC-AF51-0001',
        'numbering_authority' => 'synthetic_only',
    ], $collector);
    $afterReceipt = app(ApplicationDataResolver::class)->resolve($application->fresh())->toArray();

    expect($afterReceipt['official_receipts'])->toHaveCount(1)
        ->and($afterReceipt['official_receipts'][0]['accountable_form_number'])->toBe(51)
        ->and($afterReceipt['official_receipts'][0]['synthetic_number'])->toBeTrue()
        ->and($afterReceipt['official_receipts'][0]['series'])->toBeNull()
        ->and($afterReceipt['official_receipts'][0]['collection_rows'])->not->toBeEmpty()
        ->and($afterReceipt['permit']['official_receipt_bound'])->toBeTrue()
        ->and($afterReceipt['permit']['official_receipt_number'])->toBe('SYNTHETIC-AF51-0001')
        ->and($afterReceipt['permit']['released'])->toBeFalse()
        ->and($afterReceipt['permit']['valid'])->toBeFalse();
});

test('Business Permit projection preserves many lines of business and verification identity linkage', function () {
    $application = PermitApplication::factory()->create();
    LineOfBusiness::factory()->count(32)->create()->each(
        fn (LineOfBusiness $line) => $application->lines()->create([
            'line_of_business_id' => $line->id,
            'declared_gross_sales_cents' => 0,
            'capital_investment_cents' => 0,
            'quantity' => 1,
        ]),
    );

    $permit = app(ApplicationDataResolver::class)->resolve($application)->permit->toArray();

    expect($permit['lines_of_business'])->toHaveCount(32)
        ->and($permit['verification']['reference'])->toStartWith('PVA-'.$application->id.'-')
        ->and($permit['verification']['view_url'])->toContain('/permits/verify/'.$application->id.'/')
        ->and($permit['official_receipt_bound'])->toBeFalse()
        ->and($permit['blockers'])->toContain('official_receipt_number_binding');
});

test('Executable Application centers the facsimile and keeps actor-neutral work notes visible', function () {
    $component = file_get_contents(resource_path('js/components/permit-applications/ExecutableApplication.vue'));
    $note = file_get_contents(resource_path('js/components/permit-applications/ApplicationWorkNote.vue'));
    $routingTask = file_get_contents(resource_path('js/components/permit-applications/BploRoutingTaskSheet.vue'));
    $document = file_get_contents(resource_path('js/components/permit-applications/IpilExecutableDocument.vue'));
    $navigator = file_get_contents(resource_path('js/components/permit-applications/ApplicationDocumentNavigator.vue'));

    expect($component)->toContain('role="tablist"')
        ->and($component)->toContain("{ key: 'application_form', label: 'Application Form' }")
        ->and($component)->toContain("tab.key !== 'application' && tab.key !== 'processing'")
        ->and($component)->toContain(':aria-selected="artifactIsActive(tab.key)"')
        ->and($component)->toContain('data-testid="application-document-canvas"')
        ->and($component)->toContain('ApplicationDocumentNavigator')
        ->and($component)->toContain('IpilExecutableDocument')
        ->and($component)->toContain('page="page_1"')
        ->and($component)->toContain('page="page_2"')
        ->and($component)->toContain('data-testid="application-work-notes"')
        ->and($component)->toContain('application.actor_context.work_notes')
        ->and($component)->toContain('ApplicationWorkNote')
        ->and($component)->toContain('BploRoutingTaskSheet')
        ->and($component)->toContain("url.searchParams.set('task', 'bplo-routing')")
        ->and($note)->toContain('data-testid="application-work-note"')
        ->and($note)->toContain('note.actionable && note.action_url')
        ->and($note)->not->toMatch('/\bYour Task\b/i')
        ->and($component)->not->toContain('tab.status')
        ->and($component)->toContain('min-w-0')
        ->and($component)->toContain('break-words')
        ->and($component)->toContain('overflow-x-auto')
        ->and($routingTask)->toContain('data-testid="bplo-routing-task-sheet"')
        ->and($routingTask)->toContain('Record BPLO routing')
        ->and($routingTask)->toContain('Manual cleanroom confirmation required')
        ->and($navigator)->toContain('data-testid="application-document-navigator"')
        ->and($navigator)->toContain('data-testid="application-form-page-1-tab"')
        ->and($navigator)->toContain('data-testid="application-form-page-2-tab"')
        ->and($navigator)->toContain('Applicant Declaration')
        ->and($navigator)->toContain('Municipal Processing')
        ->and($navigator)->toContain('Emerging total')
        ->and($navigator)->toContain('sticky top-0')
        ->and($document)->toContain('data-testid="page-2-bplo-routing-recorded"')
        ->and($document)->toContain('BPLO routing recorded')
        ->and($document)->toContain('Page 1')
        ->and($document)->toContain('unchanged');
});

function configureApplicationDataPreview(): void
{
    config()->set([
        'stakeholder_preview.mode' => true,
        'stakeholder_preview.profile' => 'stakeholder_preview_weekend_v1',
        'stakeholder_preview.data_classification' => 'synthetic_only',
        'stakeholder_preview.pii_mode' => 'synthetic_only',
        'stakeholder_preview.production_migration_enabled' => false,
        'stakeholder_preview.production_integrations' => 'disabled',
    ]);
}
