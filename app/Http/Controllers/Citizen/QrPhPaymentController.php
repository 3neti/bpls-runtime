<?php

namespace App\Http\Controllers\Citizen;

use App\Actions\ConfirmQrPhPayment;
use App\Actions\InitiateQrPhPayment;
use App\Enums\UserPermission;
use App\Exceptions\XChangePartnerApiException;
use App\Http\Controllers\Controller;
use App\Models\PaymentSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class QrPhPaymentController extends Controller
{
    public function initiate(Request $request, int $paymentSchedule, InitiateQrPhPayment $initiate): JsonResponse
    {
        $schedule = $this->ownedSchedule($request, $paymentSchedule);

        try {
            return response()->json($initiate->handle($schedule))->header('Cache-Control', 'no-store');
        } catch (XChangePartnerApiException $exception) {
            return $this->partnerError($exception);
        } catch (LogicException) {
            return response()->json([
                'message' => 'QR Ph is not available for this payment right now.',
            ], 409);
        }
    }

    public function status(Request $request, int $paymentSchedule, ConfirmQrPhPayment $confirm): JsonResponse
    {
        $schedule = $this->ownedSchedule($request, $paymentSchedule);

        try {
            return response()->json($confirm->handle($schedule))->header('Cache-Control', 'no-store');
        } catch (XChangePartnerApiException $exception) {
            return $this->partnerError($exception);
        } catch (LogicException) {
            return response()->json([
                'message' => 'Payment confirmation is temporarily unavailable. Your BPLS obligation has not been marked paid.',
            ], 409);
        }
    }

    private function ownedSchedule(Request $request, int $paymentSchedule): PaymentSchedule
    {
        Gate::authorize(UserPermission::ViewOwnPermitApplicationFinancials->value);

        return PaymentSchedule::query()
            ->whereKey($paymentSchedule)
            ->whereHas('permitApplication', fn ($query) => $query->whereBelongsTo($request->user(), 'submittedBy'))
            ->firstOrFail();
    }

    private function partnerError(XChangePartnerApiException $exception): JsonResponse
    {
        $integrityFailure = in_array($exception->errorCode, [
            'EXTERNAL_REFERENCE_CONFLICT',
            'EXTERNAL_REFERENCE_MISMATCH',
            'PAYMENT_TERMS_CONFLICT',
            'COLLECTION_AMOUNT_MISMATCH',
            'VALIDATION_ERROR',
        ], true);

        return response()->json([
            'message' => $integrityFailure
                ? 'This payment request needs support review. Your BPLS obligation has not been changed.'
                : 'QR Ph is temporarily unavailable. Please try again shortly.',
            'support_code' => $exception->errorCode,
        ], $integrityFailure ? 409 : 503);
    }
}
