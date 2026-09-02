<?php

namespace App\Actions;

use App\Enums\AssessmentDecisionAction;
use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationItemType;
use App\Models\Assessment;
use App\Models\BploRoutingWork;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\BusinessPermitEvaluationItemRevision;
use App\Models\PaperlessPaymentOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class IssuePaperlessPaymentOrder
{
    public function handle(
        BusinessPermitEvaluationItem $item,
        BusinessPermitEvaluationItemRevision $revision,
        User $issuingActor,
    ): PaperlessPaymentOrder {
        return DB::transaction(function () use ($item, $revision, $issuingActor): PaperlessPaymentOrder {
            $item->loadMissing('evaluation.permitApplication');
            $application = $item->evaluation->permitApplication;
            $routingWorkId = data_get($item->metadata, 'bplo_routing_work_id');

            if (! is_int($routingWorkId)) {
                throw new LogicException('An office charge cannot issue a Paperless Payment Order without explicit BPLO-routed work.');
            }

            $routingWork = BploRoutingWork::query()->with('determination')->whereKey($routingWorkId)->lockForUpdate()->firstOrFail();
            if ($routingWork->determination->permit_application_id !== $application->id
                || $routingWork->office_code !== $item->responsible_party) {
                throw new LogicException('Paperless Payment Order provenance does not match the BPLO-selected office and Application.');
            }

            if ($revision->business_permit_evaluation_item_id !== $item->id
                || $item->item_type !== BusinessPermitEvaluationItemType::Charge
                || $revision->applicability !== BusinessPermitEvaluationApplicability::Applicable) {
                throw new LogicException('Only an applicable resolved office financial determination may issue a Paperless Payment Order.');
            }

            $amountCents = data_get($revision->value, 'amount_cents');
            if (! is_int($amountCents) || $amountCents < 0) {
                throw new LogicException('A Paperless Payment Order requires an explicit non-negative amount.');
            }

            if ($revision->actor_id !== $issuingActor->id) {
                throw new LogicException('The office actor who resolved the amount must issue its Paperless Payment Order.');
            }

            $existing = PaperlessPaymentOrder::query()
                ->where('business_permit_evaluation_item_revision_id', $revision->id)
                ->first();
            if ($existing instanceof PaperlessPaymentOrder) {
                return $existing->load(['routingWork', 'issuedBy', 'lines.lineOfBusiness']);
            }

            $currentAssessment = $application->assessments()->whereNull('superseded_at')->with('decision')->first();
            if ($currentAssessment instanceof Assessment
                && $currentAssessment->decision?->action !== AssessmentDecisionAction::ReturnedForCorrection) {
                throw new LogicException('A fresh office financial determination requires the prepared Assessment to be returned for correction first.');
            }

            $issuedAt = now();
            $sequence = (int) $routingWork->paymentOrders()->max('sequence') + 1;
            $routingWork->paymentOrders()
                ->whereNull('superseded_at')
                ->whereHas('evaluationItemRevision', fn ($query) => $query
                    ->where('business_permit_evaluation_item_id', $item->id))
                ->update(['superseded_at' => $issuedAt]);

            $order = $application->paperlessPaymentOrders()->create([
                'bplo_routing_work_id' => $routingWork->id,
                'business_permit_evaluation_item_revision_id' => $revision->id,
                'issued_by_id' => $issuingActor->id,
                'sequence' => $sequence,
                'status' => 'issued',
                'total_amount_cents' => $amountCents,
                'source_snapshot' => [
                    'permit_application_id' => $application->id,
                    'bplo_routing_determination_id' => $routingWork->bplo_routing_determination_id,
                    'bplo_routing_work_id' => $routingWork->id,
                    'office_code' => $routingWork->office_code,
                    'office_label' => $routingWork->office_label,
                    'required_work' => $routingWork->required_work,
                    'situational_reason' => $routingWork->situational_reason,
                    'business_permit_evaluation_item_id' => $item->id,
                    'business_permit_evaluation_item_revision_id' => $revision->id,
                    'source_classification' => $revision->source_classification->value,
                    'reason' => $revision->reason,
                    'cancellation_policy' => 'unresolved_not_implemented',
                ],
                'issued_at' => $issuedAt,
            ]);

            $order->lines()->create([
                'permit_application_line_id' => $routingWork->permit_application_line_id ?? data_get($item->metadata, 'permit_application_line_id'),
                'line_of_business_id' => $routingWork->line_of_business_id ?? data_get($item->metadata, 'line_of_business_id'),
                'code' => (string) data_get($item->metadata, 'code', str($item->key)->upper()->replace('.', '-')),
                'name' => (string) data_get($item->metadata, 'label', $item->key),
                'amount_cents' => $amountCents,
                'source_snapshot' => [
                    'scope' => data_get($item->metadata, 'charge_scope', $routingWork->line_of_business_id === null ? 'application' : 'line_of_business'),
                    'permit_application_line_id' => $routingWork->permit_application_line_id ?? data_get($item->metadata, 'permit_application_line_id'),
                    'line_of_business_id' => $routingWork->line_of_business_id ?? data_get($item->metadata, 'line_of_business_id'),
                    'amount_source' => 'concerned_office_financial_determination',
                ],
            ]);

            return $order->load(['routingWork', 'issuedBy', 'lines.lineOfBusiness']);
        });
    }
}
