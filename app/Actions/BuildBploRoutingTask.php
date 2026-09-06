<?php

namespace App\Actions;

use App\Data\Application\BploRoutingTaskData;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\UserPermission;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
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
            'treasuryLineOfBusinessAssignments.lineOfBusiness',
            'treasuryLineOfBusinessAssignments.items',
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
                'business_activity_description' => $application->business_activity_description,
                'commissioned_path' => data_get($application->metadata, 'nelson_reconciliation_v1.commissioned_path') === true,
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
            office_options: config('ipil_references.concerned_offices.items', []),
            financial_editor: $this->financialEditor($application, $viewer),
            can_determine: $determination === null
                && ($viewer?->can(UserPermission::DetermineBploRouting->value) ?? false),
            manual_confirmation_required: data_get($application->metadata, 'lifecycle_cleanroom.semantic_classification') === 'synthetic_only'
                && data_get($application->metadata, 'lifecycle_cleanroom.production_liability') === false,
        );
    }

    /** @return array<string, mixed> */
    private function financialEditor(PermitApplication $application, ?User $viewer): array
    {
        $fixedFees = FeeRule::query()
            ->where('is_active', true)
            ->where('calculation_type', FeeRuleCalculationType::Fixed->value)
            ->where('category', '!=', FeeRuleCategory::Tax->value)
            ->orderBy('name')->get();
        $offices = collect(config('ipil_references.concerned_offices.items', []));

        return [
            'catalog_status' => (string) config('ipil_references.concerned_offices.production_status'),
            'office_fee_options' => $offices->mapWithKeys(function (array $office) use ($fixedFees): array {
                $configuredCodes = collect($office['fee_rule_codes'] ?? []);
                $fees = $fixedFees->filter(fn (FeeRule $fee): bool => data_get($fee->metadata, 'responsible_office_code') === $office['code']
                    || $configuredCodes->contains($fee->code));

                return [$office['code'] => $fees->map(fn (FeeRule $fee): array => [
                    'id' => $fee->id,
                    'code' => $fee->code,
                    'name' => $fee->name,
                    'default_amount_cents' => $fee->amount_cents,
                ])->values()->all()];
            })->all(),
            'line_of_business_options' => LineOfBusiness::query()->availableToMunicipalCatalog()->orderBy('name')->get()
                ->map(fn (LineOfBusiness $line): array => [
                    'id' => $line->id,
                    'code' => $line->code,
                    'name' => $line->name,
                    'default_items' => $fixedFees->where('line_of_business_id', $line->id)->map(fn (FeeRule $fee): array => [
                        'fee_rule_id' => $fee->id,
                        'code' => $fee->code,
                        'name' => $fee->name,
                        'amount_cents' => $fee->amount_cents,
                    ])->values()->all(),
                ])->values()->all(),
            'treasury_assignments' => $application->treasuryLineOfBusinessAssignments->whereNull('removed_at')->map(fn ($assignment): array => [
                'id' => $assignment->id,
                'name' => $assignment->lineOfBusiness->name,
                'items' => $assignment->items->map(fn ($item): array => ['name' => $item->name, 'amount_cents' => $item->determined_amount_cents])->all(),
            ])->values()->all(),
            'can_confirm_payment_orders' => $viewer?->can(UserPermission::ContributeBusinessPermitEvaluations->value) ?? false,
            'can_assign_treasury_lobs' => $viewer?->can(UserPermission::CorrectEvaluationLinesOfBusiness->value) ?? false,
        ];
    }
}
