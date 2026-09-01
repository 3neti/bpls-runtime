<?php

namespace App\Actions;

use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitClearanceStatus;
use App\Models\Assessment;
use App\Models\AssessmentLine;
use App\Models\Business;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use App\Models\PermitApplicationLine;
use App\Models\TreasuryCollection;
use App\Models\User;
use Illuminate\Support\Collection;

class BuildCitizenBusinessDetail
{
    /** @return array<string, mixed>|null */
    public function handle(User $citizen, int $businessId, bool $includeFinancials, bool $includeDocuments = false): ?array
    {
        $applicationRelations = [
            'permitApplications' => fn ($query) => $query
                ->select([
                    'id',
                    'business_id',
                    'application_number',
                    'tracking_reference',
                    'type',
                    'status',
                    'application_year',
                    'created_at',
                ])
                ->orderByDesc('application_year')
                ->orderByDesc('id')
                ->with([
                    'lines:id,permit_application_id,line_of_business_id',
                    'lines.lineOfBusiness:id,code,name',
                    'clearances:id,permit_application_id,code,label,status,completed_at',
                ]),
        ];

        if ($includeDocuments) {
            $applicationRelations['permitApplications.documents'] = fn ($query) => $query
                ->select([
                    'id',
                    'permit_application_id',
                    'label',
                    'original_name',
                    'mime_type',
                    'size_bytes',
                    'uploaded_at',
                ])
                ->latest('uploaded_at');
        }

        if ($includeFinancials) {
            $applicationRelations['permitApplications.assessments'] = fn ($query) => $query
                ->select([
                    'id',
                    'permit_application_id',
                    'sequence',
                    'status',
                    'total_amount_cents',
                    'assessed_at',
                    'superseded_at',
                ])
                ->whereNull('superseded_at')
                ->with([
                    'decision:id,assessment_id,action,decided_at',
                    'lines:id,assessment_id,line_of_business_id,code,name,category,amount_cents',
                    'lines.lineOfBusiness:id,name',
                ])
                ->latest('sequence');
            $applicationRelations['permitApplications.paymentSchedules'] = fn ($query) => $query
                ->select([
                    'id',
                    'permit_application_id',
                    'assessment_id',
                    'sequence',
                    'status',
                    'payment_mode',
                    'due_on',
                    'total_amount_cents',
                    'paid_amount_cents',
                    'created_at',
                ])
                ->with([
                    'treasuryCollections' => fn ($query) => $query
                        ->select([
                            'id',
                            'payment_schedule_id',
                            'permit_application_id',
                            'assessment_id',
                            'status',
                            'channel',
                            'method',
                            'amount_cents',
                            'received_at',
                        ])
                        ->with('receipt:id,treasury_collection_id,payment_schedule_id,permit_application_id,assessment_id,status,receipt_number,amount_cents,issued_at')
                        ->oldest('received_at')
                        ->oldest('id'),
                ])
                ->latest('sequence');
        }

        $business = Business::query()
            ->whereKey($businessId)
            ->where('business_owner_id', $citizen->business_owner_id ?? 0)
            ->select([
                'id',
                'business_owner_id',
                'name',
                'trade_name',
                'registration_number',
                'address',
                'barangay',
                'ownership_type',
                'organization_name',
                'contact_number',
                'email',
                'established_on',
                'started_on',
                'registered_on',
            ])
            ->with([
                'owner:id,name',
                ...$applicationRelations,
            ])
            ->first();

        if (! $business instanceof Business) {
            return null;
        }

        $permitApplications = $business->permitApplications
            ->map(fn (PermitApplication $application, int $index): array => $this->applicationPayload(
                $application,
                $includeFinancials,
                $index === 0,
            ))
            ->values();
        $currentApplication = $permitApplications->first();

        return [
            'id' => $business->id,
            'name' => $business->name,
            'trade_name' => $business->trade_name,
            'registration_number' => $business->registration_number,
            'address' => $business->address,
            'barangay' => $business->barangay,
            'ownership_type' => $business->ownership_type,
            'organization_name' => $business->organization_name,
            'contact_number' => $business->contact_number,
            'email' => $business->email,
            'established_on' => $business->established_on?->toDateString(),
            'started_on' => $business->started_on?->toDateString(),
            'registered_on' => $business->registered_on?->toDateString(),
            'owner' => [
                'id' => $business->owner->id,
                'name' => $business->owner->name,
            ],
            'current_permit_application_id' => $currentApplication['id'] ?? null,
            'amount_due' => $currentApplication['payable'] ?? null,
            'permit_applications' => $permitApplications->all(),
            'documents_and_registration' => [
                'source' => 'canonical_business_record_and_uploaded_permit_application_documents',
                'documents' => $includeDocuments
                    ? $business->permitApplications->flatMap(fn (PermitApplication $application): array => $application->documents->map(fn (PermitApplicationDocument $document): array => [
                        'id' => $document->id,
                        'permit_application_id' => $application->id,
                        'application_year' => $application->application_year,
                        'application_type' => $application->type->value,
                        'label' => $document->label,
                        'original_name' => $document->original_name,
                        'mime_type' => $document->mime_type,
                        'size_bytes' => $document->size_bytes,
                        'uploaded_at' => $document->uploaded_at->toIso8601String(),
                    ])->all())->values()->all()
                    : [],
                'statement' => 'Only documents actually uploaded to a canonical permit application are shown. No registry evidence is inferred or imported.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function applicationPayload(PermitApplication $application, bool $includeFinancials, bool $isCurrent): array
    {
        $assessment = $includeFinancials ? $application->assessments->first() : null;
        $paymentSchedule = $includeFinancials ? $application->paymentSchedules->first() : null;

        return [
            'id' => $application->id,
            'citizen_label' => $application->application_year.' · '.match ($application->type->value) {
                'new' => 'New Business Permit',
                'renewal' => 'Renewal',
                default => str($application->type->value)->replace('_', ' ')->title()->toString(),
            },
            'record_reference' => 'Application record #'.$application->id,
            'display_reference' => 'Application record #'.$application->id,
            'official_application_number' => $application->application_number,
            'tracking_reference' => $application->tracking_reference,
            'type' => $application->type->value,
            'status' => $application->status->value,
            'application_year' => $application->application_year,
            'designation' => $isCurrent ? 'current' : 'historical',
            'saved_at' => $application->created_at?->toIso8601String(),
            'lines_of_business' => $application->lines
                ->map(fn (PermitApplicationLine $line): array => $this->lineOfBusinessPayload($line))
                ->values()
                ->all(),
            'assessment' => $assessment === null ? null : [
                'id' => $assessment->id,
                'sequence' => $assessment->sequence,
                'status' => $assessment->status->value,
                'citizen_status' => $assessment->decision?->action->value ?? $assessment->status->value,
                'total_amount_cents' => $assessment->total_amount_cents,
                'assessed_at' => $assessment->assessed_at?->toIso8601String(),
                'charge_groups' => $this->assessmentChargeGroups($assessment),
            ],
            'payable' => $paymentSchedule instanceof PaymentSchedule
                ? [
                    'id' => $paymentSchedule->id,
                    'status' => $paymentSchedule->status->value,
                    'payment_mode' => $paymentSchedule->payment_mode,
                    'due_on' => $paymentSchedule->due_on?->toDateString(),
                    'total_amount_cents' => $paymentSchedule->total_amount_cents,
                    'paid_amount_cents' => $paymentSchedule->paid_amount_cents,
                    'amount_due_cents' => in_array($paymentSchedule->status, [
                        PaymentScheduleStatus::Pending,
                        PaymentScheduleStatus::PartiallyPaid,
                    ], true)
                        ? max(0, $paymentSchedule->total_amount_cents - $paymentSchedule->paid_amount_cents)
                        : 0,
                    'payments' => $paymentSchedule->treasuryCollections
                        ->map(fn (TreasuryCollection $collection): array => [
                            'id' => $collection->id,
                            'status' => $collection->status->value,
                            'channel' => $collection->channel->value,
                            'method' => $collection->method->value,
                            'amount_cents' => $collection->amount_cents,
                            'received_at' => $collection->received_at->toIso8601String(),
                            'receipt' => $collection->receipt === null ? null : [
                                'id' => $collection->receipt->id,
                                'status' => $collection->receipt->status->value,
                                'receipt_number' => $collection->receipt->receipt_number,
                                'amount_cents' => $collection->receipt->amount_cents,
                                'issued_at' => $collection->receipt->issued_at->toIso8601String(),
                            ],
                        ])
                        ->all(),
                ]
                : null,
            'permit' => [
                'issuance_status' => 'not_issued',
                'release_status' => 'not_released',
                'status_label' => 'Permit not yet issued',
                'issued_at' => null,
                'released_at' => null,
                'artifact' => null,
                'clearances' => [
                    'completed' => $application->clearances
                        ->where('status', PermitClearanceStatus::Completed)
                        ->count(),
                    'total' => $application->clearances->count(),
                ],
                'statement' => 'This application, its Assessment, and any Payable are separate from permit issuance and release. No canonical issued permit is recorded.',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function assessmentChargeGroups(Assessment $assessment): array
    {
        $lineOfBusinessGroups = $assessment->lines
            ->whereNotNull('line_of_business_id')
            ->groupBy('line_of_business_id')
            ->map(fn (Collection $lines): array => [
                'key' => 'line_of_business_'.$lines->first()->line_of_business_id,
                'label' => $lines->first()->lineOfBusiness->name,
                'subtotal_amount_cents' => (int) $lines->sum('amount_cents'),
                'charges' => $lines
                    ->sortBy('code')
                    ->values()
                    ->map(fn (AssessmentLine $line): array => $this->assessmentLinePayload($line))
                    ->all(),
            ])
            ->values();

        $applicationCharges = $assessment->lines
            ->whereNull('line_of_business_id')
            ->sortBy('code')
            ->values()
            ->map(fn (AssessmentLine $line): array => [
                'key' => 'assessment_line_'.$line->id,
                'label' => $line->name,
                'subtotal_amount_cents' => $line->amount_cents,
                'charges' => [$this->assessmentLinePayload($line)],
            ]);

        return array_values($lineOfBusinessGroups
            ->concat($applicationCharges)
            ->values()
            ->all());
    }

    /** @return array{id: int, code: string, name: string, category: string, amount_cents: int} */
    private function assessmentLinePayload(AssessmentLine $line): array
    {
        return [
            'id' => $line->id,
            'code' => $line->code,
            'name' => $line->name,
            'category' => $line->category->value,
            'amount_cents' => $line->amount_cents,
        ];
    }

    /** @return array{code: string|null, name: string} */
    private function lineOfBusinessPayload(PermitApplicationLine $line): array
    {
        $lineOfBusiness = $line->lineOfBusiness;

        if ($lineOfBusiness === null) {
            return [
                'code' => null,
                'name' => 'Unresolved activity',
            ];
        }

        return [
            'code' => $lineOfBusiness->code,
            'name' => $lineOfBusiness->name,
        ];
    }
}
