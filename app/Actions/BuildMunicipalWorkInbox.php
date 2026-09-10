<?php

namespace App\Actions;

use App\Enums\AssessmentDecisionAction;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Models\Assessment;
use App\Models\BploRoutingWork;
use App\Models\InstitutionalPositionAssignment;
use App\Models\LifecycleCleanroomRun;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PostPaymentOfficeCertification;
use App\Models\ProvisionalUatPermitCompletion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class BuildMunicipalWorkInbox
{
    /**
     * @param  array{q?: string, task?: string, year?: int|null}  $filters
     * @return array{items: Collection<int, array<string, mixed>>, assignments: Collection<int, array{position: string, role: string}>, task_options: Collection<int, array{value: string, label: string}>}
     */
    public function handle(User $user, array $filters = []): array
    {
        $assignments = InstitutionalPositionAssignment::query()
            ->with('position.capabilityRole')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->get();
        $roles = $assignments->pluck('position.capabilityRole.code')->filter()->unique()->values();
        $workItems = [];

        if ($roles->contains('bplo')) {
            $workItems = [...$workItems, ...$this->bploRouting()];
        }
        foreach ($roles->intersect(['assessor', 'engineering', 'health', 'menro', 'mpdo']) as $officeCode) {
            $workItems = [...$workItems, ...$this->officePaymentOrders((string) $officeCode)];
            $workItems = [...$workItems, ...$this->officeCertifications((string) $officeCode)];
        }
        if ($roles->contains('treasury')) {
            $workItems = [...$workItems, ...$this->treasuryClassification()];
            $workItems = [...$workItems, ...$this->treasuryCounterChecks()];
        }
        if ($roles->contains('assessment_officer')) {
            $workItems = [...$workItems, ...$this->assessmentPreparation()];
            $workItems = [...$workItems, ...$this->paymentSchedulePreparation()];
        }
        if ($roles->contains('municipal_treasurer')) {
            $workItems = [...$workItems, ...$this->treasurerDecisions()];
        }
        if ($roles->contains('cashier')) {
            $workItems = [...$workItems, ...$this->cashierWork()];
        }
        if ($roles->contains('mayor_office')) {
            $workItems = [...$workItems, ...$this->permitIssuance()];
        }
        if ($roles->contains('releasing')) {
            $workItems = [...$workItems, ...$this->permitRelease()];
        }

        $query = Str::lower(trim((string) ($filters['q'] ?? '')));
        $task = (string) ($filters['task'] ?? '');
        $year = isset($filters['year']) ? (int) $filters['year'] : null;
        $items = collect($workItems)
            ->unique('key')
            ->filter(function (array $item) use ($query, $task, $year): bool {
                if ($task !== '' && $item['task_type'] !== $task) {
                    return false;
                }
                if ($year !== null && $item['application']['year'] !== $year) {
                    return false;
                }
                if ($query === '') {
                    return true;
                }

                return Str::contains(Str::lower(implode(' ', [
                    $item['application']['business_name'],
                    $item['application']['owner_name'],
                    $item['application']['tracking_reference'],
                    $item['application']['application_number'],
                ])), $query);
            })
            ->sortByDesc('received_at')
            ->values();

        return [
            'items' => $items,
            'assignments' => $assignments->map(fn (InstitutionalPositionAssignment $assignment): array => [
                'position' => $assignment->position->name,
                'role' => $assignment->position->capabilityRole->code,
            ])->values(),
            'task_options' => $items->map(fn (array $item): array => [
                'value' => (string) $item['task_type'],
                'label' => (string) $item['task_label'],
            ])->unique('value')->sortBy('label')->values(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function bploRouting(): array
    {
        return PermitApplication::query()
            ->with('business.owner')
            ->whereNotNull('submitted_at')
            ->whereNotIn('status', [PermitApplicationStatus::Draft, PermitApplicationStatus::Cancelled, PermitApplicationStatus::HistoricalEvidence, PermitApplicationStatus::Released])
            ->whereDoesntHave('bploRoutingDetermination')
            ->get()
            ->map(fn (PermitApplication $application): array => $this->item($application, 'bplo_routing', 'Determine concerned offices', 'BPLO', $application->submitted_at, 'staff.permit-applications.evaluation.show'))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function officePaymentOrders(string $officeCode): array
    {
        return BploRoutingWork::query()
            ->with(['determination.permitApplication.business.owner', 'paymentOrders'])
            ->where('office_code', $officeCode)
            ->get()
            ->filter(fn (BploRoutingWork $work): bool => ! $work->paymentOrders->contains(
                fn ($order): bool => $order->status === 'issued' && $order->superseded_at === null,
            ))
            ->groupBy(fn (BploRoutingWork $work): int => $work->determination->permit_application_id)
            ->map(function (Collection $works): array {
                /** @var BploRoutingWork $work */
                $work = $works->first();

                return $this->item(
                    $work->determination->permitApplication,
                    'payment_order',
                    'Prepare Payment Order',
                    $work->office_label,
                    $work->created_at,
                    'staff.permit-applications.evaluation.show',
                    $work->id,
                );
            })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function treasuryClassification(): array
    {
        return PermitApplication::query()
            ->with(['business.owner', 'bploRoutingDetermination.works.paymentOrders'])
            ->whereHas('bploRoutingDetermination.works')
            ->whereDoesntHave('treasuryLineOfBusinessAssignments', fn ($query) => $query->whereNull('removed_at'))
            ->get()
            ->filter(function (PermitApplication $application): bool {
                $works = $application->bploRoutingDetermination->works;

                return $works->isNotEmpty() && $works->every(fn (BploRoutingWork $work): bool => $work->paymentOrders->contains(
                    fn ($order): bool => $order->status === 'issued' && $order->superseded_at === null,
                ));
            })
            ->map(fn (PermitApplication $application): array => $this->item($application, 'treasury_classification', 'Assign official Lines of Business', 'Treasury', $application->paperlessPaymentOrders()->max('issued_at'), 'staff.permit-applications.evaluation.show'))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function assessmentPreparation(): array
    {
        return PermitApplication::query()
            ->with(['business.owner', 'treasuryLineOfBusinessAssignments'])
            ->whereHas('businessPermitEvaluation')
            ->whereHas('treasuryLineOfBusinessAssignments', fn ($query) => $query->whereNull('removed_at'))
            ->whereDoesntHave('assessments')
            ->get()
            ->map(fn (PermitApplication $application): array => $this->item($application, 'assessment_preparation', 'Prepare Assessment', 'Assessment', $application->treasuryLineOfBusinessAssignments->max('assigned_at'), 'staff.permit-applications.evaluation.show'))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function treasuryCounterChecks(): array
    {
        return Assessment::query()
            ->with('permitApplication.business.owner')
            ->whereNull('superseded_at')
            ->whereDoesntHave('treasuryCounterCheck')
            ->get()
            ->map(fn (Assessment $assessment): array => $this->item($assessment->permitApplication, 'treasury_counter_check', 'Counter-check Assessment', 'Treasury', $assessment->assessed_at, 'staff.permit-applications.evaluation.show', $assessment->id))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function treasurerDecisions(): array
    {
        return Assessment::query()
            ->with('permitApplication.business.owner')
            ->whereNull('superseded_at')
            ->whereHas('treasuryCounterCheck')
            ->whereDoesntHave('decision')
            ->get()
            ->map(fn (Assessment $assessment): array => $this->item($assessment->permitApplication, 'treasurer_decision', 'Review Assessment', 'Municipal Treasurer', $assessment->updated_at, 'staff.permit-applications.assessments.show', $assessment->id, ['assessment' => $assessment]))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function paymentSchedulePreparation(): array
    {
        return Assessment::query()
            ->with(['permitApplication.business.owner', 'decision'])
            ->whereNull('superseded_at')
            ->whereHas('decision', fn ($query) => $query->where('action', AssessmentDecisionAction::Approved))
            ->whereDoesntHave('paymentSchedules')
            ->get()
            ->map(fn (Assessment $assessment): array => $this->item($assessment->permitApplication, 'payment_schedule', 'Prepare Payment Schedule', 'Assessment', $assessment->decision->decided_at, 'staff.permit-applications.assessments.show', $assessment->id, ['assessment' => $assessment]))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function cashierWork(): array
    {
        return PaymentSchedule::query()
            ->with(['permitApplication.business.owner', 'treasuryCollections', 'xChangePayment.attempts'])
            ->where(function ($query): void {
                $query->whereIn('status', [PaymentScheduleStatus::Pending, PaymentScheduleStatus::PartiallyPaid])
                    ->orWhereHas('treasuryCollections', fn ($query) => $query->where('status', TreasuryCollectionStatus::PendingReceipt));
            })
            ->get()
            ->filter(function (PaymentSchedule $schedule): bool {
                $runId = data_get($schedule->permitApplication->metadata, 'lifecycle_cleanroom.run_id');
                $run = is_string($runId)
                    ? LifecycleCleanroomRun::query()->where('public_id', $runId)->first()
                    : null;
                if (! $run instanceof LifecycleCleanroomRun || ! $run->isClassicLifecycleV1() || $run->status !== 'active') {
                    return true;
                }
                if ($schedule->treasuryCollections->contains(fn ($collection): bool => $collection->status === TreasuryCollectionStatus::PendingReceipt)) {
                    return true;
                }

                $attempt = $schedule->xChangePayment?->attempts->sortByDesc('id')->first();

                return $attempt !== null
                    && in_array($attempt->status, ['requested', 'awaiting_payment'], true)
                    && $attempt->expires_at?->isFuture() === true;
            })
            ->map(function (PaymentSchedule $schedule): array {
                $pendingReceipts = $schedule->treasuryCollections->contains(fn ($collection): bool => $collection->status === TreasuryCollectionStatus::PendingReceipt);

                return $this->item(
                    $schedule->permitApplication,
                    $pendingReceipts ? 'receipt_issuance' : 'collection',
                    $pendingReceipts ? 'Issue Official Receipts' : 'Collect Payment',
                    'Cashier',
                    $schedule->created_at,
                    'staff.payment-schedules.show',
                    $schedule->id,
                    ['paymentSchedule' => $schedule],
                );
            })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function officeCertifications(string $officeCode): array
    {
        return PostPaymentOfficeCertification::query()
            ->with('permitApplication.business.owner')
            ->where('office_code', $officeCode)
            ->where('status', 'pending')
            ->get()
            ->map(fn (PostPaymentOfficeCertification $certification): array => $this->item($certification->permitApplication, 'post_payment_certification', 'Certify payment and receipt', $certification->office_label, $certification->created_at, 'staff.permit-applications.evaluation.show', $certification->id))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function permitIssuance(): array
    {
        return PermitApplication::query()
            ->with(['business.owner', 'postPaymentOfficeCertifications', 'provisionalUatPermitCompletion'])
            ->whereHas('postPaymentOfficeCertifications')
            ->whereDoesntHave('postPaymentOfficeCertifications', fn ($query) => $query->where('status', '!=', 'completed'))
            ->get()
            ->filter(fn (PermitApplication $application): bool => $application->provisionalUatPermitCompletion?->issued_at === null)
            ->map(fn (PermitApplication $application): array => $this->item($application, 'permit_issuance', 'Authorize and issue Permit', "Mayor's Office", $application->postPaymentOfficeCertifications->max('certified_at'), 'staff.permit-applications.show'))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function permitRelease(): array
    {
        return ProvisionalUatPermitCompletion::query()
            ->with('permitApplication.business.owner')
            ->whereNotNull('issued_at')
            ->whereNull('released_at')
            ->get()
            ->map(fn (ProvisionalUatPermitCompletion $completion): array => $this->item($completion->permitApplication, 'permit_release', 'Release Business Permit', 'BPLO Releasing', $completion->issued_at, 'staff.permit-applications.show', $completion->id))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $routeParameters
     * @return array<string, mixed>
     */
    private function item(PermitApplication $application, string $type, string $label, string $office, mixed $receivedAt, string $routeName, ?int $sourceId = null, array $routeParameters = []): array
    {
        $runId = data_get($application->metadata, 'lifecycle_cleanroom.run_id');
        $classicRun = is_string($runId)
            ? LifecycleCleanroomRun::query()->where('public_id', $runId)->first()
            : null;
        if ($classicRun instanceof LifecycleCleanroomRun
            && $classicRun->isClassicLifecycleV1()
            && in_array($type, ['post_payment_certification', 'permit_issuance', 'permit_release'], true)) {
            $routeName = 'stakeholder-preview.lifecycle-cleanroom-application.show';
            $routeParameters = [$classicRun];
        }

        $url = route($routeName, $routeParameters === [] ? $application : $routeParameters, false);
        $receivedAtIso = $receivedAt instanceof \DateTimeInterface
            ? Carbon::instance($receivedAt)->toIso8601String()
            : (is_string($receivedAt) && $receivedAt !== ''
                ? Carbon::parse($receivedAt)->toIso8601String()
                : $application->updated_at?->toIso8601String());

        return [
            'key' => $type.':'.($sourceId ?? $application->id),
            'task_type' => $type,
            'task_label' => $label,
            'office_label' => $office,
            'state' => 'action_required',
            'received_at' => $receivedAtIso,
            'action_url' => $url,
            'application' => [
                'id' => $application->id,
                'business_name' => $application->business->name,
                'owner_name' => $application->business->owner->name,
                'application_number' => $application->application_number,
                'tracking_reference' => $application->tracking_reference,
                'type' => $application->type->value,
                'year' => $application->application_year,
            ],
        ];
    }
}
