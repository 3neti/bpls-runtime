<?php

use App\Actions\BuildCollectionsByRevenueSourceReport;
use App\Actions\BuildDailyCollectionsReport;
use App\Actions\BuildPaymentSummaryReport;
use App\Actions\IssueManualCollectionReceipt;
use App\Assessment\AssessmentSnapshotFingerprint;
use App\Enums\AssessmentDecisionAction;
use App\Enums\AssessmentStatus;
use App\Enums\FeeRuleCategory;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\TreasuryCollectionChannel;
use App\Enums\TreasuryCollectionMethod;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\AssessmentDecision;
use App\Models\AssessmentLine;
use App\Models\PaymentSchedule;
use App\Models\PaymentScheduleLine;
use App\Models\PermitApplication;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use App\Models\User;
use App\Models\XChangePayment;
use App\Models\XChangePaymentAttempt;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('cache.default', 'array');
    config()->set('services.x_change', [
        'base_url' => 'https://x-change.example.test',
        'token_endpoint' => '/oauth/token',
        'client_id' => 'synthetic-client',
        'client_secret' => 'synthetic-secret',
        'scope' => 'pay-codes:estimate pay-codes:issue pay-codes:read pay-codes:pay',
        'settlement_rail' => 'INSTAPAY',
        'token_refresh_leeway_seconds' => 60,
        'connect_timeout_seconds' => 2,
        'timeout_seconds' => 5,
    ]);
    Cache::flush();
});

test('QR Ph is refused before exact Treasurer approval', function () {
    [$citizen, $schedule] = qrPhScheduleFixture(false);
    Http::preventStrayRequests();

    $this->actingAs($citizen)
        ->postJson(route('citizen.payment-schedules.qr-ph.initiate', $schedule))
        ->assertConflict()
        ->assertJsonPath('message', 'QR Ph is not available for this payment right now.');

    expect(XChangePayment::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('QR Ph initiation sends the exact approved amount and returns the x-change PNG unchanged', function () {
    [$citizen, $schedule] = qrPhScheduleFixture();
    $png = base64_encode("\x89PNG\r\n\x1a\nunchanged");
    fakeQrPhIssueAndAttempt($schedule, $png);

    $this->actingAs($citizen)
        ->postJson(route('citizen.payment-schedules.qr-ph.initiate', $schedule))
        ->assertOk()
        ->assertJsonPath('amount_cents', 12_550)
        ->assertJsonPath('status', 'awaiting_payment')
        ->assertJsonPath('qr_data_url', 'data:image/png;base64,'.$png);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/partner/v1/pay-codes')
        && $request['amount'] === 125.5
        && $request['target_amount'] === 125.5
        && $request['cash']['settlement_rail'] === 'INSTAPAY');
});

test('eligible citizen payment detail exposes the QR Ph action without partner internals', function () {
    [$citizen, $schedule] = qrPhScheduleFixture();

    $this->actingAs($citizen)
        ->get(route('citizen.payment-schedules.show', $schedule))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('citizen/payment-schedules/Show')
            ->where('paymentSchedule.online_payment_boundary.status', 'available')
            ->where('paymentSchedule.online_payment_boundary.can_pay_online', true)
            ->where('paymentSchedule.balance_amount_cents', 12_550)
            ->missing('paymentSchedule.x_change')
            ->missing('paymentSchedule.online_payment_boundary.pay_code')
            ->missing('paymentSchedule.online_payment_boundary.provider'));
});

test('an expired QR receives a new attempt key against the same payable obligation', function () {
    [$citizen, $schedule] = qrPhScheduleFixture();
    fakeQrPhIssueAndAttempt($schedule, base64_encode("\x89PNG\r\n\x1a\nfresh"));

    $this->actingAs($citizen)->postJson(route('citizen.payment-schedules.qr-ph.initiate', $schedule))->assertOk();
    XChangePaymentAttempt::query()->sole()->update([
        'status' => 'expired',
        'expires_at' => now()->subMinute(),
    ]);
    $this->actingAs($citizen)->postJson(route('citizen.payment-schedules.qr-ph.initiate', $schedule))->assertOk();

    $payment = XChangePayment::query()->sole();
    expect(XChangePayment::query()->count())->toBe(1)
        ->and($payment->pay_code)->toBe('TEST')
        ->and($payment->attempts()->count())->toBe(2)
        ->and($payment->attempts()->pluck('idempotency_key')->unique())->toHaveCount(2);

    expect(collect(Http::recorded())->filter(
        fn (array $record): bool => str_ends_with($record[0]->url(), '/api/partner/v1/pay-codes'),
    ))->toHaveCount(1);
});

test('changed assessment evidence cannot reuse payable authority', function () {
    [$citizen, $schedule] = qrPhScheduleFixture();
    $schedule->assessment->lines()->firstOrFail()->update(['amount_cents' => 13_000]);
    $schedule->assessment->update(['total_amount_cents' => 13_000]);
    Http::preventStrayRequests();

    $this->actingAs($citizen)
        ->postJson(route('citizen.payment-schedules.qr-ph.initiate', $schedule))
        ->assertConflict();

    expect(XChangePayment::query()->count())->toBe(0);
});

test('expired unpaid inquiry creates no BPLS collection or receipt', function () {
    [$citizen, $schedule] = qrPhScheduleFixture();
    fakeQrPhIssueAndAttempt($schedule, base64_encode("\x89PNG\r\n\x1a\nexpired"), false, true);
    $this->actingAs($citizen)->postJson(route('citizen.payment-schedules.qr-ph.initiate', $schedule))->assertOk();

    $this->actingAs($citizen)
        ->getJson(route('citizen.payment-schedules.qr-ph.status', $schedule))
        ->assertOk()
        ->assertJsonPath('paid', false)
        ->assertJsonPath('status', 'expired');

    expect(TreasuryCollection::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0)
        ->and($schedule->refresh()->status)->toBe(PaymentScheduleStatus::Pending);
});

test('authoritative full collection enters the existing collection receipt and report path exactly once', function () {
    [$citizen, $schedule] = qrPhScheduleFixture();
    fakeQrPhIssueAndAttempt($schedule, base64_encode("\x89PNG\r\n\x1a\npaid"), true, false);
    $this->actingAs($citizen)->postJson(route('citizen.payment-schedules.qr-ph.initiate', $schedule))->assertOk();

    $this->actingAs($citizen)
        ->getJson(route('citizen.payment-schedules.qr-ph.status', $schedule))
        ->assertOk()
        ->assertJsonPath('paid', true);
    $this->actingAs($citizen)
        ->getJson(route('citizen.payment-schedules.qr-ph.status', $schedule))
        ->assertOk()
        ->assertJsonPath('paid', true);

    $collection = TreasuryCollection::query()->sole();
    expect($collection->channel)->toBe(TreasuryCollectionChannel::Online)
        ->and($collection->method)->toBe(TreasuryCollectionMethod::QrPh)
        ->and($collection->amount_cents)->toBe(12_550)
        ->and($collection->allocations()->count())->toBe(1)
        ->and($schedule->refresh()->status)->toBe(PaymentScheduleStatus::Paid)
        ->and(TreasuryCollection::query()->count())->toBe(1);

    $receipt = app(IssueManualCollectionReceipt::class)->handle($collection, [
        'receipt_number' => '7654321',
        'numbering_authority' => 'manual',
    ], User::factory()->create());

    $filters = ['year' => $schedule->permitApplication->application_year];
    $daily = app(BuildDailyCollectionsReport::class)->handle($filters);
    $revenue = app(BuildCollectionsByRevenueSourceReport::class)->handle($filters);
    $summary = app(BuildPaymentSummaryReport::class)->handle($filters);
    $dailyRow = collect($daily['rows'])->firstWhere('collection_id', $collection->id);
    $revenueRow = collect($revenue['rows'])->firstWhere('code', 'QR-PH-FEE');
    $summaryRow = collect($summary['rows'])->firstWhere('payment_schedule_id', $schedule->id);

    expect($receipt->receipt_number)->toBe('7654321')
        ->and($dailyRow['receipt_number'])->toBe('7654321')
        ->and($dailyRow['channel'])->toBe(TreasuryCollectionChannel::Online->value)
        ->and($dailyRow['method'])->toBe(TreasuryCollectionMethod::QrPh->value)
        ->and($dailyRow['amount_cents'])->toBe(12_550)
        ->and($revenueRow['amount_cents'])->toBe(12_550)
        ->and($revenueRow['receipt_count'])->toBe(1)
        ->and($summaryRow['paid_amount_cents'])->toBe(12_550)
        ->and($summaryRow['receipted_amount_cents'])->toBe(12_550)
        ->and($summaryRow['collection_methods'])->toBe([TreasuryCollectionMethod::QrPh->value]);
});

/** @return array{User, PaymentSchedule} */
function qrPhScheduleFixture(bool $approved = true): array
{
    $citizen = userWithPermissions([
        UserPermission::AccessCitizen,
        UserPermission::ViewOwnPermitApplications,
        UserPermission::ViewOwnPermitApplicationFinancials,
    ], UserRole::Citizen);
    $application = PermitApplication::factory()->for($citizen, 'submittedBy')->create([
        'status' => PermitApplicationStatus::PendingPayment,
        'application_year' => 2026,
    ]);
    linkPortalUserToApplicationOwner($citizen, $application);
    $assessment = Assessment::factory()->for($application)->create([
        'status' => AssessmentStatus::Computed,
        'total_amount_cents' => 12_550,
    ]);
    $assessmentLine = AssessmentLine::factory()->for($assessment)->create([
        'code' => 'QR-PH-FEE',
        'name' => 'QR Ph synthetic fee',
        'category' => FeeRuleCategory::Fee,
        'amount_cents' => 12_550,
    ]);

    if ($approved) {
        AssessmentDecision::factory()->for($assessment)->create([
            'action' => AssessmentDecisionAction::Approved,
            'assessment_snapshot_hash' => app(AssessmentSnapshotFingerprint::class)->hash($assessment->fresh()),
            'total_amount_cents' => 12_550,
        ]);
    }

    $schedule = PaymentSchedule::factory()->for($application, 'permitApplication')->for($assessment)->create([
        'status' => PaymentScheduleStatus::Pending,
        'total_amount_cents' => 12_550,
        'paid_amount_cents' => 0,
    ]);
    PaymentScheduleLine::factory()->for($schedule)->create([
        'assessment_line_id' => $assessmentLine->id,
        'code' => 'QR-PH-FEE',
        'name' => 'QR Ph synthetic fee',
        'category' => FeeRuleCategory::Fee,
        'amount_cents' => 12_550,
        'paid_amount_cents' => 0,
    ]);

    return [$citizen, $schedule];
}

function fakeQrPhIssueAndAttempt(PaymentSchedule $schedule, string $png, bool $paid = false, bool $terminal = false): void
{
    $attemptNumber = 0;

    Http::fake(function (Request $request) use ($schedule, $png, $paid, $terminal, &$attemptNumber) {
        if (str_ends_with($request->url(), '/oauth/token')) {
            return Http::response(['expires_in' => 900, 'access_token' => 'synthetic-token']);
        }

        if (str_ends_with($request->url(), '/payment-attempts')) {
            $attemptNumber++;

            return Http::response([
                'data' => ['attempt' => [
                    'reference' => 'attempt-reference-'.$attemptNumber,
                    'status' => 'awaiting_payment',
                    'provider' => 'synthetic-provider',
                    'amount_minor' => $schedule->total_amount_cents,
                    'expires_at' => now()->addMinutes(10)->toIso8601String(),
                    'qr_code' => ['mime_type' => 'image/png', 'base64_payload' => $png],
                ]],
            ], 201);
        }

        if ($request->method() === 'GET') {
            return Http::response(qrPhInquiry($schedule, $paid, $terminal));
        }

        return Http::response([
            'data' => [
                'voucher_id' => 192,
                'code' => 'TEST',
                'external_reference' => $request['external_reference'],
                'consumer_status' => 'payable',
                'links' => ['pay' => 'https://x-change.example.test/x/pay/TEST'],
            ],
        ], 201);
    });
}

/** @return array<string, mixed> */
function qrPhInquiry(PaymentSchedule $schedule, bool $paid, bool $terminal): array
{
    $payment = XChangePayment::query()->where('payment_schedule_id', $schedule->id)->sole();

    return [
        'data' => [
            'external_reference' => $payment->external_reference,
            'consumer_status' => $paid ? 'collected' : 'processing',
            'status' => ['key' => $terminal ? 'expired' : 'active', 'is_terminal' => $terminal],
            'collection' => [
                'collected_total_minor' => $paid ? $schedule->total_amount_cents : 0,
                'target_amount_minor' => $schedule->total_amount_cents,
                'is_fully_collected' => $paid,
            ],
        ],
    ];
}
