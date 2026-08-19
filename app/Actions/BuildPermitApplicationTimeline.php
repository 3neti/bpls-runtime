<?php

namespace App\Actions;

use App\Enums\PermitClearanceStatus;
use App\Models\PermitApplication;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BuildPermitApplicationTimeline
{
    /**
     * Build a chronological read model from authoritative lifecycle records.
     *
     * @return list<array<string, mixed>>
     */
    public function handle(PermitApplication $permitApplication): array
    {
        $permitApplication->loadMissing([
            'business',
            'submittedBy',
            'documents.uploadedBy',
            'assessments.assessedBy',
            'assessments.decision.decidedBy',
            'paymentSchedules.preparedBy',
            'treasuryCollections.receivedBy',
            'treasuryCollections.receipt.issuedBy',
            'clearances.completedBy',
        ]);

        $metadataActors = $this->metadataActors($permitApplication);
        $events = collect();

        $events->push($this->event(
            key: "application-recorded:{$permitApplication->id}",
            category: 'application',
            title: 'Permit application recorded',
            description: sprintf('%s application recorded for %s.', str($permitApplication->type->value)->replace('_', ' ')->title(), $permitApplication->business->name),
            status: 'recorded',
            actor: $this->actor($permitApplication->submittedBy),
            occurredAt: $permitApplication->created_at,
            sourceType: 'permit_application',
            sourceId: $permitApplication->id,
        ));

        $citizenSubmission = $permitApplication->metadata['citizen_submission'] ?? null;

        if (is_array($citizenSubmission)) {
            $actorId = isset($citizenSubmission['actor_id']) ? (int) $citizenSubmission['actor_id'] : null;
            $events->push($this->event(
                key: "citizen-submitted:{$permitApplication->id}",
                category: 'application',
                title: 'Citizen submitted application',
                description: 'Citizen formally submitted the application to the municipal processing queue.',
                status: 'submitted',
                actor: $this->actor($actorId === null ? null : $metadataActors->get($actorId)),
                occurredAt: $this->date($citizenSubmission['submitted_at'] ?? null),
                sourceType: 'citizen_submission',
                sourceId: $permitApplication->id,
            ));
        }

        $municipalReceipt = $permitApplication->metadata['municipal_receipt'] ?? null;

        if (is_array($municipalReceipt)) {
            $events->push($this->event(
                key: "municipality-received:{$permitApplication->id}",
                category: 'application',
                title: 'Municipality received application',
                description: 'Municipality received the application into its assessment queue.',
                status: 'received',
                actor: null,
                occurredAt: $this->date($municipalReceipt['received_at'] ?? null),
                sourceType: 'municipal_receipt',
                sourceId: $permitApplication->id,
            ));
        }

        foreach ($permitApplication->assessments as $assessment) {
            $events->push($this->event(
                key: "assessment-computed:{$assessment->id}",
                category: 'assessment',
                title: 'Assessment computed',
                description: sprintf('Assessment #%d totals %s.', $assessment->sequence, $this->money($assessment->total_amount_cents)),
                status: $assessment->status->value,
                actor: $this->actor($assessment->assessedBy),
                occurredAt: $assessment->assessed_at ?? $assessment->created_at,
                sourceType: 'assessment',
                sourceId: $assessment->id,
            ));

            if ($assessment->decision !== null) {
                $decision = $assessment->decision;
                $decidedBy = $decision->decidedBy;
                $events->push($this->event(
                    key: "assessment-decision:{$decision->id}",
                    category: 'assessment',
                    title: $decision->action->value === 'approved'
                        ? 'Assessment amount approved by Municipal Treasurer'
                        : 'Assessment returned for correction',
                    description: $decision->reason ?? sprintf(
                        'Decision recorded against assessment #%d snapshot %s.',
                        $assessment->sequence,
                        str($decision->assessment_snapshot_hash)->take(16),
                    ),
                    status: $decision->action->value,
                    actor: $this->actor($decidedBy instanceof User ? $decidedBy : null),
                    occurredAt: $decision->decided_at,
                    sourceType: 'assessment_decision',
                    sourceId: $decision->id,
                ));
            }
        }

        foreach ($permitApplication->documents as $document) {
            $events->push($this->event(
                key: "document-recorded:{$document->id}",
                category: 'document',
                title: 'Supporting document recorded',
                description: sprintf('%s (%s)', $document->label, $document->original_name),
                status: 'received',
                actor: $this->actor($document->uploadedBy),
                occurredAt: $document->uploaded_at,
                sourceType: 'permit_application_document',
                sourceId: $document->id,
            ));
        }

        foreach ($permitApplication->paymentSchedules as $paymentSchedule) {
            $events->push($this->event(
                key: "payment-schedule-prepared:{$paymentSchedule->id}",
                category: 'payment',
                title: 'Payment schedule prepared',
                description: sprintf('Payment schedule #%d prepared for %s.', $paymentSchedule->sequence, $this->money($paymentSchedule->total_amount_cents)),
                status: $paymentSchedule->status->value,
                actor: $this->actor($paymentSchedule->preparedBy),
                occurredAt: $paymentSchedule->created_at,
                sourceType: 'payment_schedule',
                sourceId: $paymentSchedule->id,
            ));
        }

        foreach ($permitApplication->metadata['status_history'] ?? [] as $index => $transition) {
            $actorId = isset($transition['actor_id']) ? (int) $transition['actor_id'] : null;
            $events->push($this->event(
                key: "status-transition:{$index}",
                category: 'application',
                title: 'Application status changed',
                description: sprintf('%s to %s. %s', $this->label((string) ($transition['from'] ?? 'unknown')), $this->label((string) ($transition['to'] ?? 'unknown')), (string) ($transition['reason'] ?? '')),
                status: (string) ($transition['to'] ?? 'unknown'),
                actor: $this->actor($actorId === null ? null : $metadataActors->get($actorId)),
                occurredAt: $this->date($transition['occurred_at'] ?? null),
                sourceType: 'permit_application_status_history',
                sourceId: $permitApplication->id,
            ));
        }

        foreach ($permitApplication->treasuryCollections as $collection) {
            $events->push($this->event(
                key: "collection-recorded:{$collection->id}",
                category: 'treasury',
                title: 'Collection recorded',
                description: sprintf('%s received through %s.', $this->money($collection->amount_cents), $this->label($collection->method->value)),
                status: $collection->status->value,
                actor: $this->actor($collection->receivedBy),
                occurredAt: $collection->received_at,
                sourceType: 'treasury_collection',
                sourceId: $collection->id,
            ));

            if ($collection->receipt !== null) {
                $receipt = $collection->receipt;
                $events->push($this->event(
                    key: "receipt-issued:{$receipt->id}",
                    category: 'treasury',
                    title: 'Receipt issued',
                    description: sprintf('Receipt %s recorded under %s numbering authority.', $receipt->receipt_number, $this->label($receipt->numbering_authority)),
                    status: $receipt->status->value,
                    actor: $this->actor($receipt->issuedBy),
                    occurredAt: $receipt->issued_at,
                    sourceType: 'receipt',
                    sourceId: $receipt->id,
                ));
            }
        }

        foreach ($permitApplication->clearances->where('status', PermitClearanceStatus::Completed) as $clearance) {
            $events->push($this->event(
                key: "clearance-completed:{$clearance->id}",
                category: 'clearance',
                title: 'Clearance evidence completed',
                description: $clearance->label,
                status: $clearance->status->value,
                actor: $this->actor($clearance->completedBy),
                occurredAt: $clearance->completed_at,
                sourceType: 'permit_clearance',
                sourceId: $clearance->id,
            ));
        }

        $releaseBoundary = $permitApplication->metadata['release_policy_boundary'] ?? null;

        if (is_array($releaseBoundary)) {
            $actorId = isset($releaseBoundary['actor_id']) ? (int) $releaseBoundary['actor_id'] : null;
            $events->push($this->event(
                key: "release-blocked:{$permitApplication->id}",
                category: 'authority',
                title: 'Permit release blocked',
                description: (string) ($releaseBoundary['reason'] ?? 'Permit release policy remains unresolved.'),
                status: 'policy_boundary',
                actor: $this->actor($actorId === null ? null : $metadataActors->get($actorId)),
                occurredAt: $this->date($releaseBoundary['occurred_at'] ?? null),
                sourceType: 'permit_release_policy_boundary',
                sourceId: $permitApplication->id,
            ));
        }

        return $events
            ->sortBy(fn (array $event): string => ($event['occurred_at'] ?? '').'|'.str_pad((string) $this->sourceOrder($event['source']['type']), 3, '0', STR_PAD_LEFT).'|'.$event['key'])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, User>
     */
    private function metadataActors(PermitApplication $permitApplication): Collection
    {
        $statusActorIds = collect($permitApplication->metadata['status_history'] ?? [])
            ->pluck('actor_id');
        $submissionActorId = data_get($permitApplication->metadata, 'citizen_submission.actor_id');
        $releaseActorId = data_get($permitApplication->metadata, 'release_policy_boundary.actor_id');
        $actorIds = $statusActorIds
            ->push($submissionActorId)
            ->push($releaseActorId)
            ->filter()
            ->map(fn (mixed $actorId): int => (int) $actorId)
            ->unique()
            ->values();

        return User::query()
            ->whereKey($actorIds)
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  array{id: int, name: string}|null  $actor
     * @return array<string, mixed>
     */
    private function event(string $key, string $category, string $title, string $description, string $status, ?array $actor, ?CarbonInterface $occurredAt, string $sourceType, int $sourceId): array
    {
        return [
            'key' => $key,
            'category' => $category,
            'title' => $title,
            'description' => trim($description),
            'status' => $status,
            'actor' => $actor,
            'occurred_at' => $occurredAt?->toIso8601String(),
            'source' => [
                'type' => $sourceType,
                'id' => $sourceId,
            ],
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function actor(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }

    private function date(mixed $value): ?CarbonInterface
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }

    private function money(int $amountCents): string
    {
        return 'PHP '.number_format($amountCents / 100, 2);
    }

    private function label(string $value): string
    {
        return str($value)->replace('_', ' ')->title()->toString();
    }

    private function sourceOrder(string $sourceType): int
    {
        return match ($sourceType) {
            'permit_application' => 10,
            'citizen_submission' => 11,
            'municipal_receipt' => 12,
            'permit_application_document' => 15,
            'assessment' => 20,
            'assessment_decision' => 25,
            'payment_schedule' => 30,
            'permit_application_status_history' => 40,
            'treasury_collection' => 50,
            'receipt' => 60,
            'permit_clearance' => 70,
            'permit_release_policy_boundary' => 80,
            default => 100,
        };
    }
}
