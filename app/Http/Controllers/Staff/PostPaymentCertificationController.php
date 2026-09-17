<?php

namespace App\Http\Controllers\Staff;

use App\Actions\AuthorizePostPaymentCertification;
use App\Actions\RecordPostPaymentOfficeCertification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StorePostPaymentCertificationRequest;
use App\Models\PostPaymentOfficeCertification;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PostPaymentCertificationController extends Controller
{
    public function show(Request $request, PostPaymentOfficeCertification $certification, AuthorizePostPaymentCertification $authorize): Response
    {
        abort_unless($authorize->allows($certification, $request->user()), 403);

        return Inertia::render('post-payment-certifications/Show', [
            'certification' => [
                'id' => $certification->id,
                'application_id' => $certification->permit_application_id,
                'tracking_reference' => $certification->permitApplication->tracking_reference,
                'office' => $certification->office_label,
                'status' => $certification->status,
                'result' => $certification->result,
                'remarks' => $certification->remarks,
                'certified_at' => $certification->certified_at?->toIso8601String(),
                'receipt_number' => $certification->receipt->receipt_number,
                'series' => $certification->receipt->series,
                'amount_cents' => $certification->receipt->amount_cents,
                'collection_id' => $certification->receipt->treasury_collection_id,
            ],
            'applicationUrl' => route('staff.permit-applications.evaluation.show', $certification->permitApplication, false),
            'submitUrl' => route('staff.post-payment-certifications.store', $certification, false),
        ]);
    }

    public function store(StorePostPaymentCertificationRequest $request, PostPaymentOfficeCertification $certification, RecordPostPaymentOfficeCertification $record): RedirectResponse
    {
        try {
            $record->handle($certification, $request->user(), $request->validated('result'), $request->validated('remarks'));
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['result' => $exception->getMessage()]);
        }

        return redirect()->route('staff.post-payment-certifications.show', $certification)->with('success', 'Post-payment certification recorded. UAT evidence only.');
    }
}
