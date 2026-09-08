<?php

namespace App\References;

use DateTimeImmutable;
use Symfony\Component\Yaml\Yaml;
use UnexpectedValueException;

final class NelsonConcernedOfficeFeeCatalog
{
    public function __construct(private readonly ConcernedOfficeReference $concernedOffices) {}

    /** @return array<string, mixed> */
    public function load(): array
    {
        $path = config('ipil_references.nelson_concerned_office_fee_catalog.path');
        if (! is_string($path) || ! is_file($path)) {
            throw new UnexpectedValueException('The Nelson concerned-office preview fee catalog is unavailable.');
        }

        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            throw new UnexpectedValueException('The Nelson concerned-office preview fee catalog cannot be read.');
        }

        $catalog = Yaml::parse($contents, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        if (! is_array($catalog)
            || ($catalog['schema_version'] ?? null) !== 1
            || ! $this->nonEmptyString($catalog['catalog_version'] ?? null)
            || ($catalog['classification'] ?? null) !== 'synthetic_preview'
            || ($catalog['production_authority'] ?? null) !== false
            || ($catalog['production_catalog_status'] ?? null) !== 'source_reference_received_pending_validation'
            || ($catalog['currency'] ?? null) !== 'PHP') {
            throw new UnexpectedValueException('The Nelson concerned-office preview fee catalog boundary is invalid.');
        }

        $periods = $this->periods($catalog['periods'] ?? null);
        $fees = $this->fees($catalog['fees'] ?? null);
        $this->assertOfficeMappings($fees);

        return [
            ...$catalog,
            'periods' => $periods,
            'fees' => $fees,
            'source_path' => $path,
            'source_name' => basename($path),
            'digest_sha256' => hash('sha256', $contents),
        ];
    }

    /** @return list<array{application_year: int, effective_from: string, effective_until: string}> */
    private function periods(mixed $configured): array
    {
        if (! is_array($configured) || ! array_is_list($configured) || $configured === []) {
            throw new UnexpectedValueException('The preview fee catalog requires at least one explicit application-year period.');
        }

        $periods = [];
        $years = [];
        foreach ($configured as $period) {
            if (! is_array($period)
                || ! is_int($period['application_year'] ?? null)
                || ! $this->date($period['effective_from'] ?? null)
                || ! $this->date($period['effective_until'] ?? null)) {
                throw new UnexpectedValueException('Each preview fee period requires an application year and exact effective dates.');
            }

            $year = $period['application_year'];
            if (in_array($year, $years, true)
                || $period['effective_from'] !== $year.'-01-01'
                || $period['effective_until'] !== $year.'-12-31') {
                throw new UnexpectedValueException('Preview fee periods must be unique and bound to one complete application year.');
            }

            $years[] = $year;
            $periods[] = [
                'application_year' => $year,
                'effective_from' => $period['effective_from'],
                'effective_until' => $period['effective_until'],
            ];
        }

        return $periods;
    }

    /** @return list<array{code: string, label: string, office_code: string, default_amount_minor: int, account_code: ?string}> */
    private function fees(mixed $configured): array
    {
        if (! is_array($configured) || ! array_is_list($configured) || $configured === []) {
            throw new UnexpectedValueException('The preview fee catalog requires at least one fee.');
        }

        $fees = [];
        $codes = [];
        foreach ($configured as $fee) {
            if (! is_array($fee)
                || ! $this->nonEmptyString($fee['code'] ?? null)
                || ! $this->nonEmptyString($fee['label'] ?? null)
                || ! $this->nonEmptyString($fee['office_code'] ?? null)
                || ! is_int($fee['default_amount_minor'] ?? null)
                || $fee['default_amount_minor'] < 0
                || (array_key_exists('account_code', $fee) && ! $this->nonEmptyString($fee['account_code']))) {
                throw new UnexpectedValueException('Each preview fee requires a code, label, office, and non-negative integer minor amount.');
            }

            if (in_array($fee['code'], $codes, true)) {
                throw new UnexpectedValueException('Preview fee codes must be unique within the versioned catalog.');
            }

            $semanticIdentity = str((string) $fee['code'].' '.$fee['label'])
                ->lower()
                ->replace(['-', '_'], ' ')
                ->squish()
                ->toString();
            if (str_contains($semanticIdentity, 'business tax') || str_contains($semanticIdentity, 'inspection')) {
                throw new UnexpectedValueException('Business Tax and Inspection items are prohibited in the Nelson New application preview fee catalog.');
            }

            $codes[] = $fee['code'];
            $fees[] = [
                'code' => $fee['code'],
                'label' => $fee['label'],
                'office_code' => $fee['office_code'],
                'default_amount_minor' => $fee['default_amount_minor'],
                'account_code' => isset($fee['account_code']) ? (string) $fee['account_code'] : null,
            ];
        }

        return $fees;
    }

    /** @param list<array{code: string, label: string, office_code: string, default_amount_minor: int, account_code: ?string}> $fees */
    private function assertOfficeMappings(array $fees): void
    {
        $feeOwners = [];
        foreach ($this->concernedOffices->items() as $office) {
            foreach ($office['fee_rule_codes'] ?? [] as $feeRuleCode) {
                $feeOwners[$feeRuleCode][] = $office['code'];
            }
        }

        $catalogFees = collect($fees)->keyBy('code');
        foreach ($feeOwners as $feeRuleCode => $officeCodes) {
            $fee = $catalogFees->get($feeRuleCode);
            if (! is_array($fee)) {
                throw new UnexpectedValueException("Concerned office configuration references unknown preview fee [{$feeRuleCode}].");
            }
            if (count($officeCodes) !== 1 || $officeCodes[0] !== $fee['office_code']) {
                throw new UnexpectedValueException("Preview fee [{$feeRuleCode}] does not have one matching concerned-office owner.");
            }
        }

        foreach ($fees as $fee) {
            if (! array_key_exists($fee['code'], $feeOwners)) {
                throw new UnexpectedValueException("Preview fee [{$fee['code']}] is not mapped to a concerned office.");
            }
        }
    }

    private function nonEmptyString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function date(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
