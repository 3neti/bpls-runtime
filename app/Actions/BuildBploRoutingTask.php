<?php

namespace App\Actions;

use App\Data\Application\BploRoutingTaskData;
use App\Enums\UserPermission;
use App\Models\PermitApplication;
use App\Models\User;

class BuildBploRoutingTask
{
    public function handle(PermitApplication $permitApplication, ?User $viewer): BploRoutingTaskData
    {
        $application = $permitApplication->load([
            'business.owner',
            'lines.lineOfBusiness',
            'bploRoutingSuggestion',
            'bploRoutingDetermination.determinedBy',
            'bploRoutingDetermination.works.lineOfBusiness',
            'bploRoutingDetermination.works.paymentOrders.issuedBy',
            'bploRoutingDetermination.works.paymentOrders.lines',
        ]);
        $determination = $application->bploRoutingDetermination;
        $suggestion = $application->bploRoutingSuggestion;

        return new BploRoutingTaskData(
            schema_version: 'bpls.bplo-routing-task.v1',
            application: [
                'id' => $application->id,
                'application_number' => $application->application_number,
                'tracking_reference' => $application->tracking_reference,
                'business_name' => $application->business->name,
                'owner_name' => $application->business->owner->name,
                'type' => $application->type->value,
                'year' => $application->application_year,
                'submitted_at' => $application->submitted_at?->toIso8601String(),
                'lines' => $application->lines->map(fn ($line): array => [
                    'id' => $line->id,
                    'line_of_business_id' => $line->line_of_business_id,
                    'line_of_business_name' => $line->lineOfBusiness?->name,
                ])->all(),
            ],
            routing: $determination === null ? null : [
                'id' => $determination->id,
                'determined_by' => $determination->determinedBy->name,
                'determined_at' => $determination->determined_at->toIso8601String(),
                'situational_context' => $determination->situational_context,
                'application_facts_snapshot' => $determination->application_facts_snapshot,
                'origin' => data_get($determination->application_facts_snapshot, 'routing_origin', 'bplo_confirmed'),
                'works' => $determination->works->map(fn ($work): array => [
                    'id' => $work->id,
                    'office_code' => $work->office_code,
                    'office_label' => $work->office_label,
                    'situational_reason' => $work->situational_reason,
                    'required_work' => $work->required_work,
                    'line_of_business_name' => $work->lineOfBusiness?->name,
                    'payment_orders' => $work->paymentOrders->sortBy('sequence')->values()->map(fn ($order): array => [
                        'id' => $order->id,
                        'sequence' => $order->sequence,
                        'status' => $order->superseded_at === null ? $order->status : 'superseded',
                        'total_amount_cents' => $order->total_amount_cents,
                        'issued_by' => $order->issuedBy->name,
                        'issued_at' => $order->issued_at->toIso8601String(),
                        'lines' => $order->lines->map(fn ($line): array => [
                            'id' => $line->id,
                            'code' => $line->code,
                            'name' => $line->name,
                            'amount_cents' => $line->amount_cents,
                        ])->all(),
                    ])->all(),
                ])->all(),
            ],
            suggestion: $suggestion === null ? null : [
                'id' => $suggestion->id,
                'profile_version' => $suggestion->profile_version,
                'profile_keys' => $suggestion->profile_keys,
                'status' => $suggestion->status,
                'situational_context' => $suggestion->situational_context,
                'suggested_work' => $suggestion->suggested_work,
                'lodged_at' => $suggestion->lodged_at->toIso8601String(),
                'review_due_at' => $suggestion->review_due_at->toIso8601String(),
                'resolved_at' => $suggestion->resolved_at?->toIso8601String(),
                'clock' => 'elapsed',
                'server_now' => now()->toIso8601String(),
                'production_authority' => false,
            ],
            office_options: [
                ['code' => 'engineering', 'label' => 'Engineering'],
                ['code' => 'health', 'label' => 'Health'],
                ['code' => 'assessor', 'label' => 'Municipal Assessor'],
                ['code' => 'menro', 'label' => 'MENRO'],
            ],
            can_determine: $determination === null
                && ($viewer?->can(UserPermission::DetermineBploRouting->value) ?? false),
            manual_confirmation_required: data_get($application->metadata, 'lifecycle_cleanroom.semantic_classification') === 'synthetic_only'
                && data_get($application->metadata, 'lifecycle_cleanroom.production_liability') === false,
        );
    }
}
