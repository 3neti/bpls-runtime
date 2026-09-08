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
        ->and($citizenData['payment'])->toBe($healthData['payment'])
        ->and($citizenData['schedule_of_fees'])->toBe($healthData['schedule_of_fees'])
        ->and($citizenData['attachments'])->toBe($healthData['attachments'])
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
        ->and($data['schedule_of_fees']['schema_version'])->toBe('bpls.municipal-schedule-of-fees.v1')
        ->and($data['schedule_of_fees']['application_year'])->toBe(2025)
        ->and($data['schedule_of_fees']['as_of_date'])->toBe('2025-01-01')
        ->and($data['schedule_of_fees']['categories'])->not->toBeEmpty()
        ->and($data['schedule_of_fees']['context']['kind'])->toBe('application')
        ->and($data['schedule_of_fees']['context']['state'])->toBe('assessed_snapshot')
        ->and($data['schedule_of_fees']['context']['total_amount_minor'])->toBe($assessment->total_amount_cents)
        ->and(collect($data['schedule_of_fees']['categories'])->flatMap(fn (array $category): array => $category['rows'])->pluck('application_state')->unique()->all())->toBe(['assessed'])
        ->and(collect($data['attachments'])->pluck('key')->all())->toBe([
            'schedule_of_fees',
            'payment_orders',
            'assessment',
            'qr_ph',
            'official_receipt',
            'permit',
        ])
        ->and(collect($data['attachments'])->firstWhere('key', 'assessment')['state'])->toBe('frozen')
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
    $beforeReceiptAttachment = collect($beforeReceipt['attachments'])->firstWhere('key', 'official_receipt');
    expect($beforeReceipt['payment']['collections'])->toHaveCount(1)
        ->and(data_get($beforeReceipt, 'payment.reconciliation.status'))->toBe('pending_receipts')
        ->and(data_get($beforeReceipt, 'payment.reconciliation.required_receipt_group_count'))->toBe(1)
        ->and(data_get($beforeReceipt, 'payment.reconciliation.issued_receipt_group_count'))->toBe(0)
        ->and(data_get($beforeReceipt, 'payment.reconciliation.total_receipted_cents'))->toBe(0)
        ->and(data_get($beforeReceipt, 'payment.reconciliation.unreceipted_amount_cents'))->toBe($collection->amount_cents)
        ->and(data_get($beforeReceipt, 'payment.reconciliation.totals_reconciled'))->toBeFalse()
        ->and($beforeReceipt['official_receipts'])->toBe([])
        ->and($beforeReceiptAttachment['state'])->toBe('pending')
        ->and($beforeReceiptAttachment['available'])->toBeFalse()
        ->and($beforeReceipt['permit']['official_receipt_bound'])->toBeFalse()
        ->and($beforeReceipt['permit']['valid'])->toBeFalse();

    app(IssueManualCollectionReceipt::class)->handle($collection, [
        'receipt_number' => 'SYNTHETIC-AF51-0001',
        'numbering_authority' => 'synthetic_only',
    ], $collector);
    $afterReceipt = app(ApplicationDataResolver::class)->resolve($application->fresh())->toArray();
    $receiptViewer = User::query()
        ->with('role.permissions')
        ->where('email', 'stakeholder.preview.cashier@example.test')
        ->sole();
    $authorizedReceipt = app(ApplicationDataResolver::class)->resolve($application->fresh(), $receiptViewer)->toArray();
    $receiptId = $authorizedReceipt['official_receipts'][0]['source']['receipt_id'];
    $issuedReceiptAttachment = collect($afterReceipt['attachments'])->firstWhere('key', 'official_receipt');

    expect($afterReceipt['official_receipts'])->toHaveCount(1)
        ->and($afterReceipt['official_receipts'][0]['accountable_form_number'])->toBe(51)
        ->and($afterReceipt['official_receipts'][0]['synthetic_number'])->toBeTrue()
        ->and($afterReceipt['official_receipts'][0]['series'])->toBeNull()
        ->and($afterReceipt['official_receipts'][0]['collection_rows'])->not->toBeEmpty()
        ->and($afterReceipt['official_receipts'][0]['presentation_profile']['profile_key'])->toBe('ipil-af51-nelson-v1')
        ->and($afterReceipt['official_receipts'][0]['links'])->toBe([
            'view' => null,
            'pdf' => null,
        ])
        ->and($authorizedReceipt['official_receipts'][0]['links']['view'])->toBe(route('staff.receipts.show', $receiptId, false))
        ->and($authorizedReceipt['official_receipts'][0]['links']['pdf'])->toBe(route('staff.receipts.pdf', $receiptId, false))
        ->and(data_get($afterReceipt, 'payment.reconciliation.status'))->toBe('fully_reconciled')
        ->and(data_get($afterReceipt, 'payment.reconciliation.total_receipted_cents'))->toBe($collection->amount_cents)
        ->and(data_get($afterReceipt, 'payment.reconciliation.unreceipted_amount_cents'))->toBe(0)
        ->and(data_get($afterReceipt, 'payment.reconciliation.totals_reconciled'))->toBeTrue()
        ->and($issuedReceiptAttachment['state'])->toBe('issued')
        ->and($issuedReceiptAttachment['available'])->toBeTrue()
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
    $processingSheet = file_get_contents(resource_path('js/components/permit-applications/IpilMunicipalProcessingSheet.vue'));
    $navigator = file_get_contents(resource_path('js/components/permit-applications/ApplicationDocumentNavigator.vue'));
    $paymentSheet = file_get_contents(resource_path('js/components/permit-applications/IpilPaymentContinuationSheet.vue'));
    $attachmentRail = file_get_contents(resource_path('js/components/permit-applications/ApplicationAttachmentRail.vue'));
    $schedule = file_get_contents(resource_path('js/components/permit-applications/MunicipalScheduleOfFeesSheet.vue'));
    $applicationFeeCatalogue = file_get_contents(resource_path('js/components/permit-applications/ApplicationFeeCatalogueSheet.vue'));
    $paymentOrders = file_get_contents(resource_path('js/components/permit-applications/OfficePaymentOrdersSheet.vue'));
    $officialReceipt = file_get_contents(resource_path('js/components/receipts/Af51OfficialReceipt.vue'));
    $businessPermit = file_get_contents(resource_path('js/components/permit-applications/IpilBusinessPermit.vue'));

    expect($component)->toContain('ApplicationAttachmentRail')
        ->and($component)->toContain(':attachments="application.attachments"')
        ->and($component)->toContain('ApplicationFeeCatalogueSheet')
        ->and($component)->toContain('OfficePaymentOrdersSheet')
        ->and($component)->toContain("activeTab === 'schedule_of_fees'")
        ->and($component)->toContain("activeTab === 'payment_orders'")
        ->and($attachmentRail)->toContain('role="tablist"')
        ->and($attachmentRail)->toContain(':aria-selected="activeKey === attachment.key"')
        ->and($schedule)->toContain('data-testid="municipal-schedule-of-fees-sheet"')
        ->and($schedule)->toContain('schedule.categories')
        ->and($schedule)->toContain('break-inside-avoid border-b')
        ->and($schedule)->toContain('print:table-header-group')
        ->and($schedule)->toContain('schedule-inline-revision-form')
        ->and($schedule)->toContain('canManageFeeRules && row.revision_eligible')
        ->and($schedule)->not->toContain('Not an Assessment')
        ->and($applicationFeeCatalogue)->toContain('data-testid="application-fee-catalogue-sheet"')
        ->and($applicationFeeCatalogue)->toContain('Limited to the municipal work assigned to this Application.')
        ->and($applicationFeeCatalogue)->toContain('View fee details')
        ->and($applicationFeeCatalogue)->toContain("row.revenue_code || '—'")
        ->and($paymentOrders)->toContain('data-testid="office-payment-orders-sheet"')
        ->and($component)->toContain('data-testid="application-document-canvas"')
        ->and($component)->toContain('ApplicationDocumentNavigator')
        ->and($component)->toContain('IpilExecutableDocument')
        ->and($component)->toContain('IpilBusinessPermit')
        ->and($component)->toContain(':permit="application.permit"')
        ->and($businessPermit)->toContain('data-testid="ipil-business-permit"')
        ->and($businessPermit)->toContain('permitPatternUrl')
        ->and($component)->toContain('page="page_1"')
        ->and($component)->toContain('page="page_2"')
        ->and($component)->toContain('data-testid="application-current-work-note"')
        ->and($component)->toContain('data-testid="application-activity"')
        ->and($component)->toContain('currentWorkNote')
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
        ->and($routingTask)->toContain('data-testid="concerned-office-checklist"')
        ->and($routingTask)->toContain('data-testid="concerned-office-option"')
        ->and($routingTask)->toContain('data-testid="recorded-concerned-office-list"')
        ->and($routingTask)->toContain("? 'Concerned offices confirmed'")
        ->and($routingTask)->toContain('{{ candidate.office.label }}')
        ->and($routingTask)->toContain('Record BPLO routing')
        ->and($routingTask)->toContain('Manual cleanroom confirmation required')
        ->and($routingTask)->toContain('authorizedPaymentOrderWorkIds')
        ->and($component)->toContain("officeMobileView = ref<'application' | 'payment-order'>")
        ->and($component)->toContain('Office workspace view')
        ->and($component)->toContain('officePaymentOrderNote')
        ->and($component)->toContain('office-application-work-note')
        ->and($component)->toContain("mode !== 'office'")
        ->and($navigator)->toContain('data-testid="application-document-navigator"')
        ->and($navigator)->toContain('data-testid="application-form-page-1-tab"')
        ->and($navigator)->toContain('data-testid="application-form-page-2-tab"')
        ->and($navigator)->toContain('data-testid="application-form-page-3-tab"')
        ->and($navigator)->toContain('Applicant Declaration')
        ->and($navigator)->toContain('Municipal Processing')
        ->and($navigator)->toContain('processingSummary')
        ->and($navigator)->toContain('totalLabel')
        ->and($component)->toContain("'Current total'")
        ->and($navigator)->toContain('sticky top-0')
        ->and($document)->toContain('IpilMunicipalProcessingSheet')
        ->and($document)->toContain('applicantLodgingSignature')
        ->and($document)->toContain('data-testid="applicant-lodging-signature"')
        ->and($document)->toContain('Applicant signature facsimile')
        ->and($processingSheet)->toContain('data-testid="page-2-bplo-routing-recorded"')
        ->and($processingSheet)->toContain('data-testid="page-2-commissioned-office-routing"')
        ->and($processingSheet)->toContain('A. Concerned Offices and Payment Orders')
        ->and($processingSheet)->toContain("'TBD'")
        ->and($processingSheet)->toContain('Municipal Processing Continuation Sheet')
        ->and($processingSheet)->toContain('Blank routing row')
        ->and($processingSheet)->toContain('Assessment Reference')
        ->and($processingSheet)->toContain('data-testid="page-2-payment-summary"')
        ->and($processingSheet)->toContain('Paid via')
        ->and($processingSheet)->toContain('fully reconciled')
        ->and($processingSheet)->not->toContain('document.official_receipt_reference')
        ->and($paymentSheet)->toContain('Payment Continuation Sheet')
        ->and($paymentSheet)->toContain('data-testid="application-payment-pay-code"')
        ->and($paymentSheet)->toContain('data-testid="application-payment-qr"')
        ->and($paymentSheet)->toContain('data-testid="application-payment-collected-stamp"')
        ->and($paymentSheet)->toContain('Payment details')
        ->and($paymentSheet)->toContain('paymentSource()')
        ->and($paymentSheet)->toContain('data-testid="official-receipt-packet"')
        ->and($paymentSheet)->toContain('v-for="row in receiptRows"')
        ->and($paymentSheet)->toContain(':href="row.receipt.links.view"')
        ->and($paymentSheet)->toContain('font-black uppercase">Balance</dt>')
        ->and($paymentSheet)->toContain('Total receipted')
        ->and($paymentSheet)->toContain('Fully reconciled')
        ->and($paymentSheet)->toContain("paymentRequest.value?.state === 'collected'")
        ->and($paymentSheet)->toContain('paymentRequest.value.collection_id !== null')
        ->and($component)->not->toContain('data-testid="application-official-receipt-artifact"')
        ->and($component)->not->toContain('<Af51OfficialReceipt')
        ->and($officialReceipt)->toContain('data-testid="af51-official-receipt"')
        ->and($officialReceipt)->toContain('v-if="viewUrl"')
        ->and($officialReceipt)->toContain('aria-label="Open issued Official Receipt"')
        ->and($component)->toContain('check_payment_status')
        ->and($component)->toContain('setInterval(() => void checkPayment(), 4000)');
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
