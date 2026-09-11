<?php

namespace App\Actions;

use App\Data\Application\ApplicationDataResolver;
use App\Models\LifecycleCleanroomCeremonyEvent;
use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;
use App\Models\User;

class BuildLifecycleCleanroom
{
    public function __construct(
        private readonly ResolveLifecycleCleanroomState $resolveState,
        private readonly ApplicationDataResolver $applicationDataResolver,
        private readonly BuildExecutablePermitApplicationDocument $buildDocument,
        private readonly BuildConcernedOfficePaymentOrderSummary $paymentOrderSummary,
        private readonly BuildLifecycleCleanroomEvidence $buildEvidence,
    ) {}

    /** @return array<string, mixed> */
    public function handle(?User $viewer = null): array
    {
        $active = LifecycleCleanroomRun::query()->where('status', 'active')->latest('id')->first();

        $activeState = $active instanceof LifecycleCleanroomRun ? $this->resolveState->handle($active) : null;
        $applicationId = $active instanceof LifecycleCleanroomRun
            ? ($active->renewal_application_id ?? $active->new_application_id)
            : null;
        $application = is_int($applicationId) ? PermitApplication::query()->find($applicationId) : null;
        if (is_array($activeState)) {
            $activeState['evidence_summary'] = $this->buildEvidence->handle($active);
            $activeState['classic_ceremony'] = $active->isClassicLifecycleV1()
                ? [
                    'registration_claimed' => $active->registrationInvitation()->whereNotNull('claimed_at')->exists(),
                    'event_count' => $active->ceremonyEvents()->count(),
                    'events' => $active->ceremonyEvents()->latest('sequence')->limit(20)->get()->reverse()->values()->map(fn (LifecycleCleanroomCeremonyEvent $event): array => [
                        'sequence' => $event->sequence,
                        'actor_key' => $event->actor_key,
                        'event' => $event->event,
                        'route_name' => $event->route_name,
                        'canonical_step' => $event->canonical_step,
                        'completed_stage_count' => $event->completed_stage_count,
                        'occurred_at' => $event->occurred_at->toIso8601String(),
                    ])->all(),
                ]
                : null;
            $activeState['application_data'] = $application instanceof PermitApplication
                ? $this->applicationDataResolver->resolve($application, $viewer)->toArray()
                : null;
            $activeState['application_document'] = $application instanceof PermitApplication
                ? $this->buildDocument->handle($application)
                : null;
            $activeState['concerned_office_payment_orders'] = $application instanceof PermitApplication
                && data_get($application->metadata, 'nelson_reconciliation_v1.commissioned_path') === true
                    ? $this->paymentOrderSummary->handle($application)
                    : null;
            $schedule = $application?->paymentSchedules()->with([
                'xChangePayment.attempts',
                'treasuryCollections.receipts',
                'treasuryCollections.allocations',
            ])->latest('sequence')->first();
            $collection = $schedule?->treasuryCollections->first();
            $receiptIds = $collection?->receipts->pluck('id')->values()->all() ?? [];
            $requiredReceiptGroups = $collection?->allocations->pluck('receipt_group_key')->filter()->unique()->values() ?? collect();
            $issuedReceiptGroups = $collection?->receipts->pluck('receipt_group_key')->filter()->unique()->values() ?? collect();
            $latestAttempt = $schedule?->xChangePayment?->attempts->sortByDesc('id')->first();
            $currentAttempt = $latestAttempt !== null
                && in_array($latestAttempt->status, ['requested', 'awaiting_payment'], true)
                && $latestAttempt->expires_at?->isFuture() === true;
            $activeState['payment_simulation'] = [
                'available' => $currentAttempt && $schedule->treasuryCollections->isEmpty(),
                'pay_code' => $schedule?->xChangePayment?->pay_code,
                'collection_id' => $collection?->id,
                'receipt_ids' => $receiptIds,
                'receipt_coverage_complete' => $collection !== null
                    && $requiredReceiptGroups->isNotEmpty()
                    && $requiredReceiptGroups->diff($issuedReceiptGroups)->isEmpty()
                    && $collection->receipts->sum('amount_cents') === $collection->amount_cents,
                'status' => $schedule?->treasuryCollections->isNotEmpty() ? 'collected' : ($currentAttempt ? 'awaiting_simulation' : 'not_ready'),
            ];
        }

        return [
            'active' => $activeState,
            'history' => LifecycleCleanroomRun::query()
                ->where('status', 'closed')
                ->latest('closed_at')
                ->limit(25)
                ->get()
                ->map(function (LifecycleCleanroomRun $run): array {
                    $evidence = $this->buildEvidence->handle($run);

                    return [
                        ...$evidence,
                        'view_url' => route('stakeholder-preview.lifecycle-laboratory.cleanrooms.evidence', $run, false),
                    ];
                })->all(),
        ];
    }
}
