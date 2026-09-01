<?php

namespace App\Actions;

use App\Enums\PaymentScheduleStatus;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\User;

class BuildCitizenProfile
{
    /** @return array<string, mixed> */
    public function handle(User $citizen, bool $includeFinancials): array
    {
        $businessRelations = [
            'businesses' => fn ($query) => $query
                ->select(['id', 'business_owner_id', 'name', 'trade_name'])
                ->orderBy('name')
                ->with([
                    'permitApplications' => fn ($applicationQuery) => $applicationQuery
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
                            'lines.lineOfBusiness:id,name',
                        ]),
                ]),
        ];

        if ($includeFinancials) {
            $businessRelations['businesses.permitApplications.paymentSchedules'] = fn ($query) => $query
                ->select([
                    'id',
                    'permit_application_id',
                    'sequence',
                    'status',
                    'total_amount_cents',
                    'paid_amount_cents',
                ])
                ->latest('sequence');
        }

        $owner = $citizen->businessOwner()
            ->select(['id', 'name'])
            ->with($businessRelations)
            ->first();

        if (! $owner instanceof BusinessOwner) {
            return [
                'linked' => false,
                'owner' => null,
                'businesses' => [],
            ];
        }

        return [
            'linked' => true,
            'owner' => [
                'id' => $owner->id,
                'name' => $owner->name,
            ],
            'businesses' => $owner->businesses
                ->map(function (Business $business) use ($includeFinancials): array {
                    $permitApplications = $business->permitApplications
                        ->map(fn (PermitApplication $application): array => $this->applicationPayload($application, $includeFinancials))
                        ->values();
                    $currentApplication = $permitApplications->first();

                    return [
                        'id' => $business->id,
                        'name' => $business->name,
                        'trade_name' => $business->trade_name,
                        'application_count' => $business->permitApplications->count(),
                        'current_application' => $currentApplication === null ? null : [
                            'id' => $currentApplication['id'],
                            'type' => $currentApplication['type'],
                            'status' => $currentApplication['status'],
                            'application_year' => $currentApplication['application_year'],
                        ],
                        'amount_due' => $currentApplication['payable'] ?? null,
                        'permit_applications' => $permitApplications->all(),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function applicationPayload(PermitApplication $application, bool $includeFinancials): array
    {
        $latestPaymentSchedule = $includeFinancials
            ? $application->paymentSchedules->first()
            : null;

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
                ->map(fn ($line): string => $line->lineOfBusiness->name)
                ->values()
                ->all(),
            'payable' => $latestPaymentSchedule instanceof PaymentSchedule
                ? [
                    'status' => $latestPaymentSchedule->status->value,
                    'amount_due_cents' => in_array($latestPaymentSchedule->status, [
                        PaymentScheduleStatus::Pending,
                        PaymentScheduleStatus::PartiallyPaid,
                    ], true)
                        ? max(0, $latestPaymentSchedule->total_amount_cents - $latestPaymentSchedule->paid_amount_cents)
                        : 0,
                ]
                : null,
        ];
    }
}
