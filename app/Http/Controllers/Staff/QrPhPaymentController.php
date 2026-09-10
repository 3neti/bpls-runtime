<?php

namespace App\Http\Controllers\Staff;

use App\Actions\ConfirmQrPhPayment;
use App\Actions\InitiateQrPhPayment;
use App\Enums\UserPermission;
use App\Exceptions\XChangePartnerApiException;
use App\Http\Controllers\Controller;
use App\Models\LifecycleCleanroomRun;
use App\Models\PaymentSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class QrPhPaymentController extends Controller
{
    public function initiate(PaymentSchedule $paymentSchedule, InitiateQrPhPayment $initiate): JsonResponse
    {
        Gate::authorize(UserPermission::ViewPaymentSchedules->value);
        $this->ensureStaffMayControlQrPh($paymentSchedule);

        try {
            return response()->json($initiate->handle($paymentSchedule))->header('Cache-Control', 'no-store');
        } catch (XChangePartnerApiException $exception) {
            return $this->partnerError($exception);
        } catch (LogicException) {
            return response()->json([
                'message' => 'QR Ph is not available for this payment right now.',
            ], 409);
        }
    }

    public function status(PaymentSchedule $paymentSchedule, ConfirmQrPhPayment $confirm): JsonResponse
    {
        Gate::authorize(UserPermission::ViewPaymentSchedules->value);
        $this->ensureStaffMayControlQrPh($paymentSchedule);

        try {
            return response()->json($confirm->handle($paymentSchedule))->header('Cache-Control', 'no-store');
        } catch (XChangePartnerApiException $exception) {
            return $this->partnerError($exception);
        } catch (LogicException) {
            return response()->json([
                'message' => 'Payment confirmation is temporarily unavailable. The obligation has not been marked paid.',
            ], 409);
        }
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
                ? 'This payment request needs support review. The obligation has not been changed.'
                : 'QR Ph is temporarily unavailable. Please try again shortly.',
            'support_code' => $exception->errorCode,
        ], $integrityFailure ? 409 : 503);
    }

    private function ensureStaffMayControlQrPh(PaymentSchedule $paymentSchedule): void
    {
        $runId = data_get($paymentSchedule->permitApplication->metadata, 'lifecycle_cleanroom.run_id');
        $run = is_string($runId)
            ? LifecycleCleanroomRun::query()->where('public_id', $runId)->first()
            : null;

        if ($run instanceof LifecycleCleanroomRun && $run->isClassicLifecycleV1() && $run->status === 'active') {
            abort(403, 'The Classic Lifecycle Citizen must generate and monitor QR Ph.');
        }
    }
}
