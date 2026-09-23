<?php

namespace App\Assessment;

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Models\FeeRule;
use App\Models\PricingChargeGroup;
use App\Models\RevenueAccount;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PricingDefinitionValidator
{
    /** @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    public function resolve(FeeRule $rule, int $amount, array $changes = []): array
    {
        $data = Validator::make($changes, [
            'revenue_account_id' => ['nullable', 'integer', 'exists:revenue_accounts,id'],
            'group_id' => ['nullable', 'integer', 'exists:pricing_charge_groups,id'],
            'ranges' => ['sometimes', 'array', 'min:1', 'max:100'],
            'ranges.*.min_basis_cents' => ['required', 'integer', 'min:0', 'max:99999999999999'],
            'ranges.*.max_basis_cents' => ['nullable', 'integer', 'min:0', 'max:99999999999999'],
            'ranges.*.amount_cents' => ['required', 'integer', 'min:0', 'max:99999999999999'],
        ])->validate();
        if ($amount < 0 || $amount > 99999999999999 || $rule->category !== FeeRuleCategory::Fee) {
            throw ValidationException::withMessages(['publication' => 'Publication supports non-negative fee amounts, not unresolved tax policy.']);
        }
        $accountId = array_key_exists('revenue_account_id', $data) ? $data['revenue_account_id'] : $rule->getAttribute('revenue_account_id');
        $account = $accountId === null ? null : RevenueAccount::query()->whereKey($accountId)->firstOrFail();
        if ($account !== null && ! $account->is_active) {
            throw ValidationException::withMessages(['publication' => 'Select an active revenue account.']);
        }
        $group = empty($data['group_id']) ? null : PricingChargeGroup::query()->whereKey($data['group_id'])->firstOrFail();
        $ranges = [];
        if ($rule->calculation_type === FeeRuleCalculationType::Fixed) {
            if ($rule->basis !== 'none') {
                throw ValidationException::withMessages(['publication' => 'This fee still requires a characterized determination basis.']);
            }
        } elseif ($rule->calculation_type === FeeRuleCalculationType::Formula) {
            if ($rule->basis !== 'employee_count' || data_get($rule->metadata, 'basis_unit') !== 'employee') {
                throw ValidationException::withMessages(['publication' => 'Only the established employee count × unit rate formula is supported.']);
            }
        } else {
            if (! in_array($rule->basis, ['business_area_square_meters', 'declared_gross_sales', 'capital_investment'], true)
                || $rule->ranges->contains(fn ($range) => $range->rate_basis_points !== null)) {
                throw ValidationException::withMessages(['publication' => 'Rate-based or uncharacterized brackets require a separate policy implementation.']);
            }
            $ranges = $data['ranges'] ?? $rule->ranges->map->only(['min_basis_cents', 'max_basis_cents', 'amount_cents'])->all();
            $ranges = array_map(fn (array $range): array => [
                'min_basis_cents' => (int) $range['min_basis_cents'],
                'max_basis_cents' => ($range['max_basis_cents'] ?? null) === null ? null : (int) $range['max_basis_cents'],
                'amount_cents' => (int) $range['amount_cents'],
            ], $ranges);
            usort($ranges, fn (array $left, array $right): int => $left['min_basis_cents'] <=> $right['min_basis_cents']);
            $last = -1;
            foreach ($ranges as $index => $range) {
                $max = $range['max_basis_cents'] ?? null;
                if ($last === null || $range['min_basis_cents'] <= $last || ($max !== null && $max < $range['min_basis_cents'])) {
                    throw ValidationException::withMessages(['publication' => 'Brackets must be ordered, non-overlapping and have valid bounds.']);
                }
                $ranges[$index]['max_basis_cents'] = $max;
                $last = $max;
            }
            if ($ranges === []) {
                throw ValidationException::withMessages(['publication' => 'At least one amount bracket is required.']);
            }
        }

        return ['method' => $rule->calculation_type->value, 'basis' => $rule->basis, 'amount_minor' => $amount,
            'unit_code' => data_get($rule->metadata, 'basis_unit'), 'ranges' => $ranges,
            'account' => $account?->only(['id', 'code', 'name']), 'group' => $group?->only(['id', 'code', 'name'])];
    }
}
