<?php

namespace App\Actions;

use App\Enums\FeeCatalogVersionStatus;
use App\Enums\FeeDeterminationChannel;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Models\BusinessDivision;
use App\Models\FeeCatalogVersion;
use App\Models\FeeCategory;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\RevenueAccount;
use App\References\MunicipalFeeCatalog;
use App\Support\MunicipalFeeCatalogPresentation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ImportMunicipalFeeCatalog
{
    public function __construct(
        private readonly MunicipalFeeCatalog $catalog,
        private readonly MunicipalFeeCatalogPresentation $presentation,
    ) {}

    /** @return array<string, int|string> */
    public function handle(?string $path = null): array
    {
        $source = $this->catalog->read($path);
        $this->validate($source);

        return DB::transaction(function () use ($source): array {
            $catalogData = $this->mapping($source['catalog']);
            FeeCatalogVersion::query()
                ->where('status', FeeCatalogVersionStatus::Active)
                ->where('code', '!=', $catalogData['code'])
                ->update(['status' => FeeCatalogVersionStatus::Superseded]);

            $version = FeeCatalogVersion::query()->updateOrCreate(
                ['code' => $catalogData['code']],
                [
                    'title' => $catalogData['title'],
                    'status' => FeeCatalogVersionStatus::Active,
                    'effective_from' => $catalogData['effective_from'],
                    'effective_until' => $catalogData['effective_until'] ?? null,
                    'authority_reference' => $catalogData['authority_reference'] ?? null,
                    'source_sha256' => $source['source_sha256'],
                    'metadata' => ['source_path' => basename($source['source_path'])],
                ],
            );

            $divisions = collect($this->rows($source['business_divisions']))->mapWithKeys(function (array $item): array {
                $division = BusinessDivision::query()->updateOrCreate(
                    ['code' => $item['code']],
                    [
                        'name' => $this->presentation->canonicalName($item['name']),
                        'is_active' => $item['status'] === 'active',
                        'metadata' => [...$this->mapping($item['metadata'] ?? []), 'source_name' => $item['name']],
                    ],
                );

                return [$item['code'] => $division];
            });
            $categories = collect($this->rows($source['fee_categories']))->mapWithKeys(function (array $item): array {
                $category = FeeCategory::query()->updateOrCreate(
                    ['code' => $item['code']],
                    ['name' => $item['name'], 'fee_rule_category' => $item['fee_rule_category'], 'is_active' => $item['status'] === 'active'],
                );

                return [$item['code'] => $category];
            });
            $accounts = collect($this->rows($source['revenue_accounts'] ?? []))->mapWithKeys(function (array $item): array {
                $account = RevenueAccount::query()->updateOrCreate(
                    ['code' => $item['code']],
                    ['name' => $item['name'] ?? null, 'is_active' => $item['status'] === 'active', 'metadata' => $item['metadata'] ?? null],
                );

                return [$item['code'] => $account];
            });
            $lines = collect($this->rows($source['lines_of_business']))->mapWithKeys(function (array $item) use ($divisions, $catalogData): array {
                $line = LineOfBusiness::query()
                    ->where('legacy_source_id', $item['source_key'])
                    ->orWhere('code', $item['code'])
                    ->first() ?? new LineOfBusiness;
                $line->fill([
                    'code' => $item['code'],
                    'name' => $this->presentation->canonicalName($item['name']),
                    'major_category' => $item['major_category'] ?? null,
                    'is_active' => $item['status'] === 'active',
                    'legacy_source_id' => $item['source_key'],
                    'metadata' => ['catalog_version' => $catalogData['code'], 'source_name' => $item['name']],
                ])->save();
                $line->businessDivisions()->sync(collect($this->strings($item['business_division_codes'] ?? []))->mapWithKeys(
                    fn (string $code): array => [$divisions->get($code)->id => []],
                )->all());

                return [$item['code'] => $line];
            });
            LineOfBusiness::query()
                ->whereNotIn('id', $lines->pluck('id'))
                ->whereNull('metadata->scenario_id')
                ->update(['is_active' => false]);

            $importedIds = [];
            foreach ($this->rows($source['fees']) as $item) {
                $rule = FeeRule::query()
                    ->where('legacy_source_id', $item['source_key'])
                    ->orWhere('code', $item['code'])
                    ->when(isset($item['revenue_code']), fn ($query) => $query->orWhere(function ($query) use ($item): void {
                        $query->where('name', $item['name'])->where('metadata->municipal_account_code', $item['revenue_code']);
                    }))
                    ->first() ?? new FeeRule;
                $division = isset($item['business_division_code']) ? $divisions->get($item['business_division_code']) : null;
                $category = $categories->get($item['fee_category_code']);
                $account = isset($item['revenue_code']) ? $accounts->get($item['revenue_code']) : null;
                $lineIds = collect($this->strings($item['line_of_business_codes'] ?? []))->map(fn (string $code): int => $lines->get($code)->id)->all();

                $rule->fill([
                    'fee_catalog_version_id' => $version->id,
                    'line_of_business_id' => $lineIds[0] ?? null,
                    'business_division_id' => $division?->id,
                    'code' => $item['code'],
                    'name' => $this->presentation->canonicalName($item['name']),
                    'category' => $category->fee_rule_category,
                    'fee_category_id' => $category->id,
                    'revenue_account_id' => $account?->id,
                    'scope' => $item['scope'] ?? (($lineIds === []) ? FeeRuleScope::Application : FeeRuleScope::LineOfBusiness),
                    'determination_channel' => $item['determination_channel'],
                    'calculation_type' => $item['calculation_type'],
                    'basis' => $item['basis'],
                    'amount_cents' => $item['amount_minor'],
                    'effective_from' => $catalogData['effective_from'],
                    'effective_until' => $catalogData['effective_until'] ?? null,
                    'legal_basis' => $catalogData['authority_reference'] ?? null,
                    'is_active' => $item['status'] === 'active',
                    'legacy_source_id' => $item['source_key'],
                    'metadata' => [
                        'catalog_status' => $item['status'],
                        'catalog_version' => $catalogData['code'],
                        'source_name' => $item['name'],
                        'application_types' => $item['application_types'] ?? [],
                        'formula' => $item['formula'] ?? null,
                        'basis_unit' => $item['basis_unit'] ?? null,
                        'unit_amount_minor' => $item['unit_amount_minor'] ?? null,
                        'exact_once_key' => $item['exact_once_key'] ?? null,
                        'evidence_reference' => $catalogData['evidence_reference'] ?? null,
                        'manual_amount_required' => ! $this->hasExecutableCalculation($item),
                        'responsible_office_code' => $item['office_code'] ?? null,
                        'price_list_source_classification' => 'migrated_legacy_uat',
                    ],
                ])->save();
                $importedIds[] = $rule->id;
                $rule->lineOfBusinesses()->sync(collect($lineIds)->mapWithKeys(fn (int $id): array => [$id => ['source' => 'municipal_fee_catalog']])->all());
                $rule->officeAssignments()->delete();
                if (isset($item['office_code'], $item['office_name'])) {
                    $rule->officeAssignments()->create([
                        'office_code' => $item['office_code'],
                        'office_label' => $item['office_name'],
                        'source' => 'municipal_fee_catalog',
                    ]);
                }
                $rule->ranges()->delete();
                foreach ($this->rows($item['ranges'] ?? []) as $range) {
                    $rule->ranges()->create([
                        'min_basis_cents' => $range['minimum_basis_minor'],
                        'max_basis_cents' => $range['maximum_basis_minor'] ?? null,
                        'amount_cents' => $range['amount_minor'],
                        'rate_basis_points' => null,
                    ]);
                }
            }

            FeeRule::query()
                ->whereNotIn('id', $importedIds)
                ->whereNull('metadata->scenario_id')
                ->whereNull('metadata->semantic_classification')
                ->update(['is_active' => false]);
            FeeRule::query()
                ->whereNotIn('id', $importedIds)
                ->where('code', 'like', 'LAB-NELSON-%')
                ->update(['is_active' => false]);

            return [
                'catalog_version' => $version->code,
                'business_divisions' => $divisions->count(),
                'lines_of_business' => $lines->count(),
                'fee_categories' => $categories->count(),
                'revenue_accounts' => $accounts->count(),
                'fees' => count($importedIds),
            ];
        });
    }

    /** @param array<string, mixed> $source */
    private function validate(array $source): void
    {
        $validator = validator($source, [
            'catalog.code' => ['required', 'string'],
            'catalog.title' => ['required', 'string'],
            'catalog.effective_from' => ['required', 'date_format:Y-m-d'],
            'business_divisions.*.code' => ['required', 'string', 'distinct'],
            'business_divisions.*.name' => ['required', 'string'],
            'business_divisions.*.status' => ['required', Rule::in(['active', 'inactive'])],
            'lines_of_business.*.code' => ['required', 'string', 'distinct'],
            'lines_of_business.*.source_key' => ['required', 'string', 'distinct'],
            'lines_of_business.*.name' => ['required', 'string'],
            'lines_of_business.*.status' => ['required', Rule::in(['active', 'inactive'])],
            'fee_categories.*.code' => ['required', 'string', 'distinct'],
            'fee_categories.*.fee_rule_category' => ['required', Rule::enum(FeeRuleCategory::class)],
            'fees.*.code' => ['required', 'string', 'distinct'],
            'fees.*.source_key' => ['required', 'string', 'distinct'],
            'fees.*.name' => ['required', 'string'],
            'fees.*.fee_category_code' => ['required', 'string'],
            'fees.*.determination_channel' => ['required', Rule::enum(FeeDeterminationChannel::class)],
            'fees.*.calculation_type' => ['required', Rule::enum(FeeRuleCalculationType::class)],
            'fees.*.scope' => ['sometimes', Rule::enum(FeeRuleScope::class)],
            'fees.*.basis' => ['required', 'string'],
            'fees.*.basis_unit' => ['sometimes', 'string'],
            'fees.*.unit_amount_minor' => ['sometimes', 'integer', 'min:0'],
            'fees.*.exact_once_key' => ['sometimes', 'string'],
            'fees.*.amount_minor' => ['required', 'integer', 'min:0'],
            'fees.*.status' => ['required', Rule::in(['active', 'incomplete', 'inactive'])],
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $divisionCodes = collect($this->rows($source['business_divisions']))->pluck('code');
        $lineCodes = collect($this->rows($source['lines_of_business']))->pluck('code');
        $categoryCodes = collect($this->rows($source['fee_categories']))->pluck('code');
        $revenueCodes = collect($this->rows($source['revenue_accounts'] ?? []))->pluck('code');
        foreach ($this->rows($source['lines_of_business']) as $line) {
            if (collect($this->strings($line['business_division_codes'] ?? []))->diff($divisionCodes)->isNotEmpty()) {
                throw new \LogicException("Line of Business [{$line['code']}] references an unknown Business Division.");
            }
        }
        foreach ($this->rows($source['fees']) as $fee) {
            if (! $categoryCodes->contains($fee['fee_category_code'])
                || (isset($fee['business_division_code']) && ! $divisionCodes->contains($fee['business_division_code']))
                || collect($this->strings($fee['line_of_business_codes'] ?? []))->diff($lineCodes)->isNotEmpty()
                || (isset($fee['revenue_code']) && ! $revenueCodes->contains($fee['revenue_code']))) {
                throw new \LogicException("Fee [{$fee['code']}] contains an unknown catalogue relationship.");
            }
        }
    }

    /** @return array<string, mixed> */
    private function mapping(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** @param array<string, mixed> $item */
    private function hasExecutableCalculation(array $item): bool
    {
        if ($item['calculation_type'] === FeeRuleCalculationType::Fixed->value) {
            return $item['amount_minor'] > 0;
        }

        if ($item['calculation_type'] === FeeRuleCalculationType::Formula->value) {
            return $item['basis'] === 'employee_count'
                && ($item['basis_unit'] ?? null) === 'employee'
                && is_int($item['unit_amount_minor'] ?? null);
        }

        return $item['calculation_type'] === FeeRuleCalculationType::Range->value
            && $item['basis'] === 'business_area_square_meters'
            && ($item['basis_unit'] ?? null) === 'centi_square_meter'
            && $this->rows($item['ranges'] ?? []) !== [];
    }

    /** @return list<array<string, mixed>> */
    private function rows(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_array(...))) : [];
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }
}
