<?php

namespace App\Http\Requests\Staff;

use App\Enums\TreasuryCollectionMethod;
use App\Enums\UserPermission;
use App\Models\PaymentSchedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentScheduleCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(UserPermission::RecordCollections->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxPesos = number_format($this->paymentScheduleBalanceCents() / 100, 2, '.', '');

        return [
            'amount_pesos' => ['required', 'numeric', 'min:0.01', 'max:'.$maxPesos],
            'method' => ['required', Rule::enum(TreasuryCollectionMethod::class)],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array{amount_cents: int, method: string, payer_name?: string|null, reference_number?: string|null, remarks?: string|null}
     */
    public function validatedForCollection(): array
    {
        $validated = $this->validated();

        return [
            'amount_cents' => $this->pesosToCents($validated['amount_pesos']),
            'method' => $validated['method'],
            'payer_name' => $validated['payer_name'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ];
    }

    private function paymentScheduleBalanceCents(): int
    {
        $paymentSchedule = $this->route('paymentSchedule');

        if (! $paymentSchedule instanceof PaymentSchedule) {
            return 0;
        }

        return max(0, $paymentSchedule->total_amount_cents - $paymentSchedule->paid_amount_cents);
    }

    private function pesosToCents(mixed $amount): int
    {
        [$pesos, $centavos] = array_pad(explode('.', (string) $amount, 2), 2, '0');

        return ((int) $pesos * 100) + (int) str_pad(substr($centavos, 0, 2), 2, '0');
    }
}
