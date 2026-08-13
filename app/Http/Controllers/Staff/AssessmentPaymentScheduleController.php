<?php

namespace App\Http\Controllers\Staff;

use App\Actions\CreatePaymentScheduleForAssessment;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\PaymentSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentPaymentScheduleController extends Controller
{
    public function store(Assessment $assessment, CreatePaymentScheduleForAssessment $createPaymentSchedule): RedirectResponse
    {
        Gate::authorize(UserPermission::PreparePaymentSchedules->value);

        $paymentSchedule = $createPaymentSchedule->handle($assessment, auth()->user());

        return to_route('staff.payment-schedules.show', $paymentSchedule);
    }

    public function show(PaymentSchedule $paymentSchedule): Response
    {
        Gate::authorize(UserPermission::ViewPaymentSchedules->value);

        $paymentSchedule->load([
            'preparedBy',
            'assessment',
            'permitApplication.business.owner',
            'lines.lineOfBusiness',
        ]);

        return Inertia::render('payment-schedules/Show', [
            'paymentSchedule' => $this->paymentSchedulePayload($paymentSchedule),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentSchedulePayload(PaymentSchedule $paymentSchedule): array
    {
        return [
            'id' => $paymentSchedule->id,
            'sequence' => $paymentSchedule->sequence,
            'status' => $paymentSchedule->status->value,
            'payment_mode' => $paymentSchedule->payment_mode,
            'due_on' => $paymentSchedule->due_on?->toDateString(),
            'total_amount_cents' => $paymentSchedule->total_amount_cents,
            'paid_amount_cents' => $paymentSchedule->paid_amount_cents,
            'prepared_by' => $paymentSchedule->preparedBy?->name,
            'created_at' => $paymentSchedule->created_at?->toIso8601String(),
            'assessment' => [
                'id' => $paymentSchedule->assessment->id,
                'sequence' => $paymentSchedule->assessment->sequence,
                'status' => $paymentSchedule->assessment->status->value,
            ],
            'permit_application' => [
                'id' => $paymentSchedule->permitApplication->id,
                'application_number' => $paymentSchedule->permitApplication->application_number,
                'type' => $paymentSchedule->permitApplication->type->value,
                'status' => $paymentSchedule->permitApplication->status->value,
                'application_year' => $paymentSchedule->permitApplication->application_year,
                'business_name' => $paymentSchedule->permitApplication->business->name,
                'owner_name' => $paymentSchedule->permitApplication->business->owner->name,
            ],
            'lines' => $paymentSchedule->lines
                ->sortBy('code')
                ->values()
                ->map(fn ($line): array => [
                    'id' => $line->id,
                    'assessment_line_id' => $line->assessment_line_id,
                    'code' => $line->code,
                    'name' => $line->name,
                    'category' => $line->category->value,
                    'due_on' => $line->due_on?->toDateString(),
                    'status' => $line->status->value,
                    'amount_cents' => $line->amount_cents,
                    'paid_amount_cents' => $line->paid_amount_cents,
                    'line_of_business' => $line->lineOfBusiness?->name,
                ]),
        ];
    }
}
