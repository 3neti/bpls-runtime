<?php

namespace App\Console\Commands;

use App\Enums\BillingGroupAcceptanceStatus;
use App\Enums\BillingGroupRecordStatus;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitClearanceStatus;
use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\LifecycleScenarios\ScenarioArtifactStore;
use App\Models\BillingGroup;
use App\Models\BillingGroupRecord;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('lifecycle:finalize-stakeholder-preview-evidence {run-id : Prepared stakeholder preview run reference}')]
#[Description('Audit canonical preview state against a completed managed-browser report and freeze the private evidence manifest.')]
class FinalizeStakeholderPreviewEvidenceCommand extends Command
{
    public function handle(): int
    {
        try {
            $this->assertSafeEnvironment();
            $runId = (string) $this->argument('run-id');
            $store = new ScenarioArtifactStore('stakeholder_preview_cycle_1', $runId);
            $manifest = $store->readJson('manifest.json') ?? throw new RuntimeException('Stakeholder preview manifest is missing.');
            $browser = $store->readJson('browser/managed-report.json') ?? throw new RuntimeException('Managed-browser report is missing.');

            if (data_get($manifest, 'scenario.key') !== 'stakeholder_preview_cycle_1') {
                throw new RuntimeException('Manifest is not a stakeholder preview composition.');
            }

            $checks = $this->checks($manifest, $browser, $store);
            $passed = collect($checks)->every(fn (array $check): bool => $check['passed']);
            $store->putJson('terminal/managed-audit.json', [
                'checks' => $checks,
                'passed' => $passed,
                'occurred_at' => now()->toIso8601String(),
            ]);

            if (! $passed) {
                throw new RuntimeException('Stakeholder preview managed audit failed.');
            }

            $manifest['result']['browser'] = 'passed';
            $manifest['result']['audit'] = 'passed';
            $manifest['result']['passed'] = data_get($manifest, 'result.terminal') === 'passed';
            $manifest['artifacts']['managed_browser_report'] = 'browser/managed-report.json';
            $manifest['artifacts']['managed_audit'] = 'terminal/managed-audit.json';
            $manifest['artifacts']['screenshots'] = data_get($browser, 'artifacts.screenshots', []);
            $manifest['preview']['managed_acceptance'] = [
                'status' => 'passed',
                'check_count' => data_get($browser, 'result.check_count'),
                'screenshot_count' => data_get($browser, 'result.screenshot_count'),
                'application_console_error_or_warning_count' => data_get($browser, 'result.application_console_error_or_warning_count'),
                'failed_internal_request_count' => data_get($browser, 'result.failed_internal_request_count'),
                'unexpected_external_resource_count' => data_get($browser, 'result.unexpected_external_resource_count'),
                'horizontal_overflow_count' => data_get($browser, 'result.horizontal_overflow_count'),
                'verified_at' => now()->toIso8601String(),
            ];
            $store->putJson('manifest.json', $manifest);
            $store->put('preview-summary.md', $this->summary($manifest));

            $this->info('Stakeholder preview evidence passed and was frozen.');
            $this->line('Run ID: '.$runId);
            $this->line('Checks: '.data_get($browser, 'result.check_count'));
            $this->line('Screenshots: '.data_get($browser, 'result.screenshot_count'));
            $this->line('Artifacts: '.$store->absolutePath());

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function assertSafeEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Stakeholder preview evidence finalization is allowed only in local or testing.');
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $browser
     * @return list<array{key: string, expected: mixed, actual: mixed, passed: bool}>
     */
    private function checks(array $manifest, array $browser, ScenarioArtifactStore $store): array
    {
        $application = PermitApplication::query()->whereKey((int) data_get($manifest, 'resources.permit_application_id'))->sole();
        $schedule = PaymentSchedule::query()->whereKey((int) data_get($manifest, 'resources.payment_schedule_id'))->sole();
        $collection = TreasuryCollection::query()->whereKey((int) data_get($manifest, 'resources.collection_id'))->sole();
        $receipt = Receipt::query()->whereKey((int) data_get($manifest, 'resources.receipt_id'))->sole();
        $billingGroup = BillingGroup::query()->whereKey((int) data_get($manifest, 'preview.billing_group.id'))->sole();
        $billingRecord = BillingGroupRecord::query()->whereKey((int) data_get($manifest, 'preview.billing_group.record_id'))->sole();
        $screenshots = data_get($browser, 'artifacts.screenshots', []);
        $screenshotFilesPresent = is_array($screenshots) && collect($screenshots)->every(
            fn (string $path): bool => Storage::disk('local')->exists($store->rootRelativePath().'/'.$path),
        );

        return [
            $this->check('terminal-preparation', 'passed', data_get($manifest, 'result.terminal')),
            $this->check('managed-browser-result', true, data_get($browser, 'result.passed')),
            $this->check('managed-browser-check-count', data_get($browser, 'result.check_count'), count(data_get($browser, 'checks', []))),
            $this->check('managed-screenshots-present', true, $screenshotFilesPresent),
            $this->check('application-numbering-boundary', null, $application->application_number),
            $this->check('payment-schedule-state', PaymentScheduleStatus::Paid->value, $schedule->status->value),
            $this->check('collection-state', TreasuryCollectionStatus::Receipted->value, $collection->status->value),
            $this->check('receipt-state', ReceiptStatus::Issued->value, $receipt->status->value),
            $this->check(
                'clearance-state',
                (int) data_get($manifest, 'resources.clearances_total'),
                $application->clearances()->where('status', PermitClearanceStatus::Completed->value)->count(),
            ),
            $this->check('release-boundary', false, (bool) data_get($manifest, 'resources.can_release')),
            $this->check('issuance-boundary', false, (bool) data_get($manifest, 'resources.can_issue')),
            $this->check('legal-effect-boundary', false, (bool) data_get($manifest, 'resources.can_make_legally_effective')),
            $this->check('billing-group-policy', BillingGroupAcceptanceStatus::Provisional->value, $billingGroup->acceptance_status->value),
            $this->check('billing-record-state', BillingGroupRecordStatus::Draft->value, $billingRecord->status->value),
            $this->check('billing-record-financial-effect', 'none', data_get($billingRecord->source_snapshot, 'financial_effect')),
            $this->check('synthetic-data-classification', 'synthetic_uat_only', data_get($manifest, 'preview.data_classification')),
            $this->check('production-migration-boundary', false, data_get($manifest, 'preview.production_migration_executed')),
            $this->check('application-console-errors', 0, data_get($browser, 'result.application_console_error_or_warning_count')),
            $this->check('failed-internal-requests', 0, data_get($browser, 'result.failed_internal_request_count')),
            $this->check('unexpected-external-resources', 0, data_get($browser, 'result.unexpected_external_resource_count')),
            $this->check('horizontal-overflow', 0, data_get($browser, 'result.horizontal_overflow_count')),
        ];
    }

    /** @return array{key: string, expected: mixed, actual: mixed, passed: bool} */
    private function check(string $key, mixed $expected, mixed $actual): array
    {
        return [
            'key' => $key,
            'expected' => $expected,
            'actual' => $actual,
            'passed' => $expected === $actual,
        ];
    }

    /** @param array<string, mixed> $manifest */
    private function summary(array $manifest): string
    {
        return '# Stakeholder Preview Evidence'.PHP_EOL.PHP_EOL
            .'- Run: `'.data_get($manifest, 'run_id').'`'.PHP_EOL
            .'- Data: synthetic stakeholder preview / UAT only'.PHP_EOL
            .'- Terminal preparation: passed'.PHP_EOL
            .'- Managed browser acceptance: passed'.PHP_EOL
            .'- Browser checks: '.data_get($manifest, 'preview.managed_acceptance.check_count').PHP_EOL
            .'- Screenshots: '.data_get($manifest, 'preview.managed_acceptance.screenshot_count').PHP_EOL
            .'- Application console errors or warnings: 0'.PHP_EOL
            .'- Failed internal requests: 0'.PHP_EOL
            .'- Unexpected application resources: 0'.PHP_EOL
            .'- Page-level horizontal overflow: 0'.PHP_EOL
            .'- Permit issuance, release, and legal effect: unavailable'.PHP_EOL
            .'- Billing-group financial execution: blocked pending municipal policy'.PHP_EOL
            .'- Production migration: unexecuted'.PHP_EOL;
    }
}
