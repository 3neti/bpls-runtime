<?php

namespace App\Actions;

use App\Enums\AssessmentDecisionAction;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Models\PermitApplication;
use App\Models\Receipt;
use LogicException;

final class PostPaymentCertificationEligibility
{
    public function ordinaryUat(PermitApplication $application): bool
    {
        $url = rtrim((string) config('app.url'), '/');

        return data_get($application->metadata, 'nelson_reconciliation_v1.commissioned_path') === true
            && data_get($application->metadata, 'lifecycle_cleanroom.run_id') === null
            && app()->environment(['staging', 'local', 'testing'])
            && config('stakeholder_preview.mode') === true
            && config('stakeholder_preview.production_migration_enabled') === false
            && config('stakeholder_preview.production_integrations') === 'disabled'
            && ($url === 'https://bpls-stakeholder-preview-uat-uat-5wn03n.laravel.cloud'
                || (app()->environment(['local', 'testing'])
                    && in_array(parse_url($url, PHP_URL_HOST), ['localhost', '127.0.0.1', 'bpls-runtime.test'], true)));
    }

    /** @return array<string, Receipt> Exact routing-office to receipt binding. */
    public function receipts(PermitApplication $application): array
    {
        $cleanroom = data_get($application->metadata, 'lifecycle_cleanroom.semantic_classification') === 'synthetic_only'
            && data_get($application->metadata, 'lifecycle_cleanroom.production_liability') === false;
        if ((! $cleanroom && ! $this->ordinaryUat($application))
            || $application->isHistoricalEvidenceOnly()
            || $application->status !== PermitApplicationStatus::PendingPayment
            || $application->provisionalUatPermitCompletion?->issued_at !== null) {
            throw new LogicException('This Application is not eligible for UAT post-payment certification.');
        }
        $application->load(['bploRoutingDetermination.works', 'paymentSchedules.assessment.decision', 'paymentSchedules.treasuryCollections.receipts', 'paymentSchedules.treasuryCollections.allocations']);
        $routing = $application->bploRoutingDetermination;
        $schedule = $application->paymentSchedules->sortByDesc('sequence')->first();
        if ($routing === null || $routing->works->isEmpty() || $schedule === null
            || $schedule->status === PaymentScheduleStatus::Voided
            || $schedule->assessment->superseded_at !== null
            || $schedule->assessment->decision?->action !== AssessmentDecisionAction::Approved
            || $schedule->assessment->total_amount_cents !== $schedule->total_amount_cents
            || $schedule->paid_amount_cents !== $schedule->total_amount_cents
            || $schedule->treasuryCollections->count() !== 1) {
            throw new LogicException('Actual routing and a fully paid canonical schedule are required.');
        }
        $collection = $schedule->treasuryCollections->sole();
        $receipts = $collection->receipts;
        $allocations = $collection->allocations;
        if ($collection->status !== TreasuryCollectionStatus::Receipted
            || $collection->amount_cents !== $schedule->total_amount_cents
            || $collection->assessment_id !== $schedule->assessment_id
            || $collection->permit_application_id !== $application->id
            || $receipts->isEmpty()
            || $receipts->contains(fn ($r): bool => $r->status !== ReceiptStatus::Issued || blank($r->receipt_number)
                || $r->assessment_id !== $schedule->assessment_id || $r->payment_schedule_id !== $schedule->id || $r->permit_application_id !== $application->id)
            || $receipts->sum('amount_cents') !== $collection->amount_cents
            || $allocations->isEmpty()
            || $allocations->sum('amount_cents') !== $collection->amount_cents
            || $allocations->contains(fn ($a): bool => ! $receipts->contains(fn ($r): bool => $r->id === $a->receipt_id && $r->receipt_group_key === $a->receipt_group_key))
            || $receipts->contains(fn ($r): bool => $allocations->where('receipt_id', $r->id)->sum('amount_cents') !== $r->amount_cents)) {
            throw new LogicException('Complete reconciled canonical Official Receipt coverage is required before post-payment office certification.');
        }
        $bindings = [];
        if ($this->ordinaryUat($application)) {
            foreach ($routing->works as $work) {
                if ($application->paperlessPaymentOrders()->where('bplo_routing_work_id', $work->id)
                    ->where('status', 'issued')->whereNull('superseded_at')->count() !== 1) {
                    throw new LogicException('Each routed certification office requires its exact finalized Payment Order.');
                }
            }
        }
        foreach ($routing->works->pluck('office_code')->unique() as $office) {
            $matches = $receipts->where('receipt_group_key', 'office:'.$office);
            if ($matches->count() !== 1) {
                throw new LogicException("The routed {$office} office requires exactly one matching Official Receipt group.");
            }
            $bindings[$office] = $matches->sole();
        }

        return $bindings;
    }
}
