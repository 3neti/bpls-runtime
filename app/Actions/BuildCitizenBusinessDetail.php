<?php

namespace App\Actions;

use App\Enums\PaymentScheduleStatus;
use App\Models\Business;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\User;

class BuildCitizenBusinessDetail
{
    /** @return array<string, mixed>|null */
    public function handle(User $citizen, int $businessId, bool $includeFinancials): ?array
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
                ->latest('id')
                ->with([
                    'lines:id,permit_application_id,line_of_business_id',
                    'lines.lineOfBusiness:id,code,name',
                ]),
        ];

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
                ->latest('sequence');
            $applicationRelations['permitApplications.paymentSchedules'] = fn ($query) => $query
                ->select([
                    'id',
                    'permit_application_id',
                    'assessment_id',
                    'sequence',
                    'status',
                    'total_amount_cents',
                    'paid_amount_cents',
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
            'permit_applications' => $business->permitApplications
                ->map(fn (PermitApplication $application): array => $this->applicationPayload($application, $includeFinancials))
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function applicationPayload(PermitApplication $application, bool $includeFinancials): array
    {
        $assessment = $includeFinancials ? $application->assessments->first() : null;
        $paymentSchedule = $includeFinancials ? $application->paymentSchedules->first() : null;

        return [
            'id' => $application->id,
            'display_reference' => $application->application_number
                ?? $application->tracking_reference
                ?? 'Application record #'.$application->id,
            'type' => $application->type->value,
            'status' => $application->status->value,
            'application_year' => $application->application_year,
            'saved_at' => $application->created_at?->toIso8601String(),
            'lines_of_business' => $application->lines
                ->map(fn (PermitApplicationLine $line): array => $this->lineOfBusinessPayload($line))
                ->values()
                ->all(),
            'assessment' => $assessment === null ? null : [
                'id' => $assessment->id,
                'sequence' => $assessment->sequence,
                'status' => $assessment->status->value,
                'total_amount_cents' => $assessment->total_amount_cents,
                'assessed_at' => $assessment->assessed_at?->toIso8601String(),
            ],
            'payable' => $paymentSchedule instanceof PaymentSchedule
                ? [
                    'id' => $paymentSchedule->id,
                    'status' => $paymentSchedule->status->value,
                    'total_amount_cents' => $paymentSchedule->total_amount_cents,
                    'paid_amount_cents' => $paymentSchedule->paid_amount_cents,
                    'amount_due_cents' => in_array($paymentSchedule->status, [
                        PaymentScheduleStatus::Pending,
                        PaymentScheduleStatus::PartiallyPaid,
                    ], true)
                        ? max(0, $paymentSchedule->total_amount_cents - $paymentSchedule->paid_amount_cents)
                        : 0,
                ]
                : null,
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
