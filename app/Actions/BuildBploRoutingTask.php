<?php

namespace App\Actions;

use App\Assessment\AssessmentCalculator;
use App\Assessment\ConcernedOfficeFeeApplicability;
use App\Data\Application\BploRoutingTaskData;
use App\Enums\FeeDeterminationChannel;
use App\Enums\FeeRuleCategory;
use App\Enums\UserPermission;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\SignatureEvidence;
use App\Models\TreasuryLineItem;
use App\Models\TreasuryLineOfBusinessAssignment;
use App\Models\User;
use App\References\ConcernedOfficeReference;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BuildBploRoutingTask
{
    public function __construct(
        private readonly ConcernedOfficeReference $concernedOffices,
        private readonly BuildConcernedOfficePaymentOrderSummary $paymentOrderSummary,
        private readonly AuthorizeRoutedOfficeActor $authorizeRoutedOfficeActor,
        private readonly AssessmentCalculator $assessmentCalculator,
        private readonly ConcernedOfficeFeeApplicability $officeFeeApplicability,
    ) {}

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
            'bploRoutingDetermination.works.paymentOrders.signatureEvidences.media',
            'treasuryLineOfBusinessAssignments.lineOfBusiness',
            'treasuryLineOfBusinessAssignments.items',
        ]);
        $determination = $application->bploRoutingDetermination;
        $suggestion = $application->bploRoutingSuggestion;
        $canViewPaymentOrderReference = $viewer?->can(UserPermission::CorrectEvaluationLinesOfBusiness->value) ?? false;

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
                    'payment_orders' => $work->paymentOrders->sortBy('sequence')->values()->map(function ($order) use ($canViewPaymentOrderReference): array {
                        $signature = $order->signatureEvidences
                            ->firstWhere('purpose', 'concerned_office_payment_order_confirmation');

                        return [
                            'id' => $order->id,
                            'sequence' => $order->sequence,
                            'status' => $order->superseded_at === null ? $order->status : 'superseded',
                            'total_amount_cents' => $order->total_amount_cents,
                            'issued_by' => $order->issuedBy->name,
                            'issued_at' => $order->issued_at->toIso8601String(),
                            'signature_facsimile_data_url' => $canViewPaymentOrderReference && $signature instanceof SignatureEvidence
                                ? $this->signatureFacsimileDataUrl($signature->getFirstMedia(SignatureEvidence::FacsimileCollection))
                                : null,
                            'lines' => $order->lines->map(fn ($line): array => [
                                'id' => $line->id,
                                'code' => $line->code,
                                'name' => $line->name,
                                'amount_cents' => $line->amount_cents,
                            ])->all(),
                        ];
                    })->all(),
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
            office_options: $this->concernedOffices->items(),
            financial_editor: $this->financialEditor($application, $viewer),
            can_determine: $determination === null
                && ($viewer?->can(UserPermission::DetermineBploRouting->value) ?? false),
            manual_confirmation_required: data_get($application->metadata, 'lifecycle_cleanroom.semantic_classification') === 'synthetic_only'
                && data_get($application->metadata, 'lifecycle_cleanroom.production_liability') === false,
        );
    }

    private function signatureFacsimileDataUrl(?Media $media): ?string
    {
        if ($media === null || ! in_array($media->mime_type, ['image/png', 'image/jpeg'], true)) {
            return null;
        }

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();
        if (! $disk->exists($path)) {
            return null;
        }

        return 'data:'.$media->mime_type.';base64,'.base64_encode($disk->get($path));
    }

    /** @return array<string, mixed> */
    private function financialEditor(PermitApplication $application, ?User $viewer): array
    {
        $periodStart = $application->application_year.'-01-01';
        $periodEnd = $application->application_year.'-12-31';
        $catalogFees = FeeRule::query()
            ->with(['lineOfBusinesses:id', 'officeAssignments', 'revenueAccount', 'ranges', 'catalogVersion', 'businessDivision'])
            ->where('is_active', true)
            ->where('category', '!=', FeeRuleCategory::Tax->value)
            ->whereDate('effective_from', '<=', $periodEnd)
            ->where(fn ($query) => $query
                ->whereNull('effective_until')
                ->orWhereDate('effective_until', '>=', $periodStart))
            ->orderBy('name')->get()
            ->filter(fn (FeeRule $fee): bool => $this->appliesToApplicationType($fee, $application));
        $offices = collect($this->concernedOffices->items());

        return [
            'catalog_status' => $this->concernedOffices->provenance()['production_catalog_status'],
            'concerned_office_payment_orders' => $this->paymentOrderSummary->handle($application),
            'office_fee_options' => $offices->mapWithKeys(function (array $office) use ($application, $catalogFees): array {
                $configuredCodes = collect($office['fee_rule_codes'] ?? []);
                $commissionedPath = data_get($application->metadata, 'nelson_reconciliation_v1.commissioned_path') === true;
                $fees = $catalogFees->filter(function (FeeRule $fee) use ($application, $commissionedPath, $configuredCodes, $office): bool {
                    if (! $this->officeFeeApplicability->matches($fee, $application, $office['code'])) {
                        return false;
                    }
                    if (! $commissionedPath && data_get($fee->metadata, 'semantic_classification') === 'synthetic_only') {
                        return false;
                    }
                    $explicitOfficeMatch = $fee->officeAssignments->contains('office_code', $office['code']);

                    return $commissionedPath
                        ? $configuredCodes->contains($fee->code) || $explicitOfficeMatch
                        : $explicitOfficeMatch || data_get($fee->metadata, 'responsible_office_code') === $office['code'];
                });

                return [$office['code'] => $fees->map(function (FeeRule $fee) use ($application, $fees): array {
                    $calculation = $this->catalogCalculation($fee, $application);

                    return [
                        'id' => $fee->id,
                        'code' => $fee->code,
                        'name' => $fees->where('name', $fee->name)->count() > 1
                            ? $fee->name.' — '.($fee->businessDivision->name ?? data_get($fee->metadata, 'legacy_division_name', 'Application')).' · '.$fee->code
                            : $this->catalogOptionName($fee),
                        'default_amount_cents' => $calculation['amount_cents'],
                        'calculation' => $calculation,
                        'scope' => $fee->scope->value,
                        'exact_once_key' => data_get($fee->metadata, 'exact_once_key'),
                        'account_code' => $fee->revenueAccount->code ?? data_get($fee->metadata, 'municipal_account_code'),
                    ];
                })->values()->all()];
            })->all(),
            'line_of_business_options' => LineOfBusiness::query()->availableToMunicipalCatalog()->orderBy('name')->get()
                ->map(fn (LineOfBusiness $line): array => [
                    'id' => $line->id,
                    'code' => $line->code,
                    'name' => $line->name,
                    'default_items' => $catalogFees->filter(fn (FeeRule $fee): bool => $fee->determination_channel === FeeDeterminationChannel::TreasuryLineOfBusiness
                        && ($fee->line_of_business_id === $line->id || $fee->lineOfBusinesses->contains('id', $line->id)))->map(function (FeeRule $fee) use ($application): array {
                            $calculation = $this->catalogCalculation($fee, $application);

                            return [
                                'fee_rule_id' => $fee->id,
                                'code' => $fee->code,
                                'name' => $this->catalogOptionName($fee),
                                'amount_cents' => $calculation['amount_cents'],
                                'calculation' => $calculation,
                                'scope' => $fee->scope->value,
                                'exact_once_key' => data_get($fee->metadata, 'exact_once_key'),
                            ];
                        })->values()->all(),
                ])->values()->all(),
            'treasury_assignments' => $application->treasuryLineOfBusinessAssignments->whereNull('removed_at')->map(fn (TreasuryLineOfBusinessAssignment $assignment): array => [
                'id' => $assignment->id,
                'name' => $assignment->lineOfBusiness->name,
                'items' => $assignment->items->map(fn (TreasuryLineItem $item): array => ['name' => $item->name, 'amount_cents' => $item->determined_amount_cents])->all(),
            ])->values()->all(),
            'authorized_payment_order_office_codes' => $viewer === null
                || ! $viewer->can(UserPermission::ContributeBusinessPermitEvaluations->value)
                ? []
                : $application->bploRoutingDetermination?->works
                    ->filter(function ($work) use ($application, $viewer): bool {
                        $authorizedActorId = data_get($work->context_snapshot, 'authorized_actor_id');

                        return $this->authorizeRoutedOfficeActor->allows(
                            $application,
                            $work->office_code,
                            $viewer,
                            is_int($authorizedActorId) ? $authorizedActorId : null,
                        );
                    })
                    ->pluck('office_code')->unique()->values()->all() ?? [],
            'can_assign_treasury_lobs' => $viewer?->can(UserPermission::CorrectEvaluationLinesOfBusiness->value) ?? false,
        ];
    }

    private function appliesToApplicationType(FeeRule $fee, PermitApplication $application): bool
    {
        $applicationTypes = data_get($fee->metadata, 'application_types');

        return ! is_array($applicationTypes)
            || $applicationTypes === []
            || in_array($application->type->value, $applicationTypes, true);
    }

    private function catalogOptionName(FeeRule $fee): string
    {
        $division = data_get($fee->metadata, 'legacy_division_name');

        return is_string($division) && trim($division) !== ''
            ? $fee->name.' — '.str($division)->lower()->headline()->toString()
            : $fee->name;
    }

    /** @return array{amount_cents: int, basis_value: int|null, basis_unit: string|null, explanation: string|null, rule_signature: string} */
    private function catalogCalculation(FeeRule $fee, PermitApplication $application): array
    {
        if (data_get($fee->metadata, 'manual_amount_required') === true) {
            return [
                'amount_cents' => $fee->amount_cents,
                'basis_value' => null,
                'basis_unit' => null,
                'explanation' => null,
                'rule_signature' => $this->ruleSignature($fee),
            ];
        }

        $calculation = $this->assessmentCalculator->calculate($fee, null, $application);
        $basisUnit = data_get($fee->metadata, 'basis_unit');
        $basis = $calculation['basis_amount_cents'];
        $explanation = match ($basisUnit) {
            'employee' => $basis.' employees × ₱'.number_format(((int) data_get($fee->metadata, 'unit_amount_minor')) / 100, 2).' = ₱'.number_format($calculation['amount_cents'] / 100, 2),
            'centi_square_meter' => number_format($basis / 100, 2).' m² · applicable area bracket = ₱'.number_format($calculation['amount_cents'] / 100, 2),
            default => null,
        };

        return [
            'amount_cents' => $calculation['amount_cents'],
            'basis_value' => $basis,
            'basis_unit' => is_string($basisUnit) ? $basisUnit : null,
            'explanation' => $explanation,
            'rule_signature' => $this->ruleSignature($fee),
        ];
    }

    private function ruleSignature(FeeRule $fee): string
    {
        return hash('sha256', json_encode([
            'name' => $fee->name,
            'calculation_type' => $fee->calculation_type->value,
            'basis' => $fee->basis,
            'basis_unit' => data_get($fee->metadata, 'basis_unit'),
            'unit_amount_minor' => data_get($fee->metadata, 'unit_amount_minor'),
            'amount_cents' => $fee->amount_cents,
            'ranges' => $fee->ranges->map->only(['min_basis_cents', 'max_basis_cents', 'amount_cents', 'rate_basis_points'])->values()->all(),
        ], JSON_THROW_ON_ERROR));
    }
}
