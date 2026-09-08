<?php

namespace App\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class BuildMunicipalScheduleOfFees
{
    public function __construct(private readonly BuildFeeMatrixQuickLook $buildFeeMatrix) {}

    /** @return array<string, mixed> */
    public function handle(
        ?CarbonInterface $asOf = null,
        ?string $search = null,
        ?string $office = null,
        ?int $lineOfBusinessId = null,
        ?int $feeRuleId = null,
        ?string $chargeCode = null,
        ?string $chargeLabel = null,
        ?string $sourceClassification = null,
        bool $includeUnconfirmedRules = false,
    ): array {
        $matrix = $this->buildFeeMatrix->handle(
            $search,
            $office,
            $lineOfBusinessId,
            $feeRuleId,
            $chargeCode,
            $chargeLabel,
            $sourceClassification,
        );

        return $this->fromMatrix($matrix, $asOf ?? now(), $includeUnconfirmedRules);
    }

    /**
     * @param  array<string, mixed>  $matrix
     * @return array<string, mixed>
     */
    public function fromMatrix(
        array $matrix,
        CarbonInterface $asOf,
        bool $includeUnconfirmedRules = false,
    ): array {
        $effectiveDate = $asOf->copy()->startOfDay();
        $directFeeRuleId = data_get($matrix, 'context.has_direct_fee_rule')
            ? data_get($matrix, 'context.fee_rule_id')
            : null;
        $feeRules = collect($this->arrayRows($matrix, 'application_wide'))
            ->concat(collect($this->arrayRows($matrix, 'line_of_businesses'))->flatMap(
                fn (array $group): array => $this->arrayRows($group, 'fees'),
            ))
            ->filter(fn (array $fee): bool => $this->isEffective($fee, $effectiveDate))
            ->filter(fn (array $fee): bool => $includeUnconfirmedRules || data_get($fee, 'status') === 'in_force')
            ->map(fn (array $fee): array => [
                'id' => 'rule-'.data_get($fee, 'id'),
                'fee_rule_id' => data_get($fee, 'id'),
                'category' => data_get($fee, 'service_category'),
                'service' => data_get($fee, 'name'),
                'basis' => data_get($fee, 'family') === 'application_wide'
                    ? 'Whole application'
                    : data_get($fee, 'line_of_business_name', 'Line of Business'),
                'code' => data_get($fee, 'code'),
                'amount_minor' => data_get($fee, 'amount_minor'),
                'rate_basis_points' => null,
                'is_ceiling' => false,
                'status' => data_get($fee, 'status') === 'in_force'
                    ? (data_get($fee, 'amount_minor') === null ? 'needs_determination' : 'available')
                    : 'for_confirmation',
                'effective_from' => data_get($fee, 'effective_from'),
                'effective_until' => data_get($fee, 'effective_until'),
                'revision_eligible' => data_get($fee, 'calculation_type') === 'fixed',
                'management_url' => data_get($fee, 'management_url'),
                'governance_url' => null,
            ])
            ->values();

        $ordinanceRows = collect($this->arrayRows($matrix, 'ordinance_register'))
            ->filter(fn (array $provision): bool => $directFeeRuleId === null
                || data_get($provision, 'linked_fee_rule.id') === $directFeeRuleId)
            ->flatMap(function (array $provision) use ($feeRules): array {
                return collect($this->arrayRows($provision, 'entries'))
                    ->filter(fn (array $entry): bool => data_get($entry, 'amount_minor') !== null
                        || data_get($entry, 'rate_basis_points') !== null)
                    ->reject(fn (array $entry): bool => $feeRules->contains(
                        fn (array $fee): bool => data_get($fee, 'management_url') === data_get($provision, 'linked_fee_rule.management_url')
                            && data_get($fee, 'amount_minor') === data_get($entry, 'amount_minor'),
                    ))
                    ->map(fn (array $entry): array => [
                        'id' => data_get($provision, 'code').'-'.data_get($entry, 'id'),
                        'fee_rule_id' => null,
                        'category' => data_get($provision, 'service_category'),
                        'service' => data_get($entry, 'service_label'),
                        'basis' => $this->joinStrings([
                            data_get($entry, 'basis_label'),
                            data_get($entry, 'unit_label'),
                        ]),
                        'code' => data_get($entry, 'code'),
                        'amount_minor' => data_get($entry, 'amount_minor'),
                        'rate_basis_points' => data_get($entry, 'rate_basis_points'),
                        'is_ceiling' => (bool) data_get($entry, 'is_ceiling'),
                        'status' => data_get($entry, 'is_ceiling') ? 'needs_determination' : 'for_confirmation',
                        'effective_from' => null,
                        'effective_until' => null,
                        'revision_eligible' => false,
                        'management_url' => data_get($provision, 'linked_fee_rule.management_url'),
                        'governance_url' => data_get($provision, 'governance_url'),
                    ])
                    ->all();
            });

        $rows = $feeRules->concat($ordinanceRows)->values();
        $categories = $rows
            ->groupBy(fn (array $row): string => (string) data_get($row, 'category.key'))
            ->map(fn (Collection $categoryRows): array => [
                'key' => (string) data_get($categoryRows->first(), 'category.key'),
                'label' => (string) data_get($categoryRows->first(), 'category.label'),
                'rows' => $categoryRows->values()->all(),
            ])
            ->values();

        return [
            'schema_version' => 'bpls.municipal-schedule-of-fees.v1',
            'title' => 'Municipal Schedule of Fees',
            'scope' => 'Business Permit and Licensing Services',
            'as_of_date' => $effectiveDate->toDateString(),
            'application_year' => (int) $effectiveDate->format('Y'),
            'currency' => 'PHP',
            'categories' => $categories->all(),
        ];
    }

    /** @param array<string, mixed> $fee */
    private function isEffective(array $fee, CarbonInterface $effectiveDate): bool
    {
        $from = data_get($fee, 'effective_from');
        $until = data_get($fee, 'effective_until');

        return (! is_string($from) || $from === '' || $from <= $effectiveDate->toDateString())
            && (! is_string($until) || $until === '' || $until >= $effectiveDate->toDateString());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function arrayRows(array $payload, string $key): array
    {
        $rows = data_get($payload, $key);

        return is_array($rows)
            ? array_values(array_filter($rows, is_array(...)))
            : [];
    }

    /** @param list<mixed> $values */
    private function joinStrings(array $values): string
    {
        return implode(' · ', array_filter($values, is_string(...)));
    }
}
