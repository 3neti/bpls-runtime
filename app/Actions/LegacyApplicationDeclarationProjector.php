<?php

namespace App\Actions;

use App\Enums\LegacyLineOfBusinessReconciliationStatus;
use App\Enums\LegacyMappingProposalStatus;
use App\Models\LegacyLineOfBusinessReconciliation;
use App\Models\LegacyRecord;
use App\Models\LineOfBusiness;
use App\Models\PermitApplicationLine;
use Illuminate\Support\Str;

class LegacyApplicationDeclarationProjector
{
    /**
     * @return array{
     *   attributes: array<string, mixed>,
     *   reconciliation: LegacyLineOfBusinessReconciliation|null,
     *   line_of_business: LineOfBusiness|null,
     *   status: LegacyMappingProposalStatus,
     *   reasons: list<string>,
     *   category_hash: string|null,
     *   capital_cents: int|null,
     *   gross_sales_cents: int|null
     * }
     */
    public function project(LegacyRecord $record, int $lineIndex): array
    {
        $lines = $record->payload['linesOfBusiness'] ?? null;
        $line = is_array($lines) ? (array_values($lines)[$lineIndex] ?? null) : null;

        if (! is_array($line)) {
            return $this->invalidProjection('legacy_declaration_line_missing');
        }

        $category = $this->string($line['businessCategory'] ?? null);
        $categoryHash = $category === '' ? null : hash('sha256', $this->normalize($category));
        $reconciliation = $categoryHash === null ? null : LegacyLineOfBusinessReconciliation::query()
            ->where('legacy_source_id', $record->legacy_source_id)
            ->where('source_dataset', 'groups')
            ->where('source_value_hash', $categoryHash)
            ->first();
        $target = $reconciliation?->line_of_business_id === null ? null : LineOfBusiness::query()->find($reconciliation->line_of_business_id);
        $reasons = [];
        $blocked = false;

        if ($categoryHash === null) {
            $reasons[] = 'business_category_missing';
            $blocked = true;
        } elseif (! $reconciliation instanceof LegacyLineOfBusinessReconciliation) {
            $reasons[] = 'accepted_line_of_business_reconciliation_missing';
            $blocked = true;
        } elseif ($reconciliation->status !== LegacyLineOfBusinessReconciliationStatus::Accepted
            || $reconciliation->decision_authority === null || $reconciliation->evidence_reference === null) {
            $reasons[] = 'line_of_business_reconciliation_not_accepted';
            $blocked = true;
        } elseif (! $target instanceof LineOfBusiness) {
            $reasons[] = 'reconciled_line_of_business_target_missing';
            $blocked = true;
        } elseif (! $target->is_active) {
            $reasons[] = 'reconciled_line_of_business_inactive';
        }

        $type = $this->string($line['permitApplicationType'] ?? $record->payload['permitApplicationType'] ?? null);
        $capital = $this->money($line['capitalInvestment'] ?? null);
        $gross = $this->grossMoney($line, $reasons);

        if ($type === 'New' && $capital === null) {
            $reasons[] = 'new_application_capital_not_exact_amount';
            $blocked = true;
        } elseif ($type === 'Renewal' && $gross === null) {
            $reasons[] = 'renewal_gross_sales_not_exact_amount';
            $blocked = true;
        } elseif (! in_array($type, ['New', 'Renewal'], true)) {
            $reasons[] = 'declaration_application_type_requires_reconciliation';
        }

        foreach (['feeOverrides', 'excludedFees', 'feeVariableMappings'] as $field) {
            if (is_array($line[$field] ?? null) && $line[$field] !== []) {
                $reasons[] = 'line_financial_configuration_migration_required';
                break;
            }
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'attributes' => [
                'line_of_business_id' => $target?->id,
                'declared_gross_sales_cents' => $gross ?? 0,
                'capital_investment_cents' => $capital ?? 0,
                'quantity' => 1,
                'started_on' => null,
                'metadata' => [
                    'legacy_number_of_employees' => is_int($line['numberOfEmployees'] ?? null) ? $line['numberOfEmployees'] : null,
                    'legacy_category_hash' => $categoryHash,
                ],
            ],
            'reconciliation' => $reconciliation,
            'line_of_business' => $target,
            'status' => $this->proposalStatus($blocked, $reasons),
            'reasons' => $reasons,
            'category_hash' => $categoryHash,
            'capital_cents' => $capital,
            'gross_sales_cents' => $gross,
        ];
    }

    /** @param array<array-key, mixed> $value */
    public function hashCanonical(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function targetSnapshotHash(PermitApplicationLine $line): string
    {
        return $this->hashCanonical([
            'id' => $line->id,
            'permit_application_id' => $line->permit_application_id,
            'line_of_business_id' => $line->line_of_business_id,
            'declared_gross_sales_cents' => $line->declared_gross_sales_cents,
            'capital_investment_cents' => $line->capital_investment_cents,
            'quantity' => $line->quantity,
            'started_on' => $line->started_on?->toDateString(),
            'metadata' => $line->metadata,
            'created_at' => $line->created_at?->toIso8601String(),
            'updated_at' => $line->updated_at?->toIso8601String(),
        ]);
    }

    /** @return array{attributes: array<string, mixed>, reconciliation: null, line_of_business: null, status: LegacyMappingProposalStatus, reasons: list<string>, category_hash: null, capital_cents: null, gross_sales_cents: null} */
    private function invalidProjection(string $reason): array
    {
        return [
            'attributes' => [
                'line_of_business_id' => null,
                'declared_gross_sales_cents' => 0,
                'capital_investment_cents' => 0,
                'quantity' => 1,
                'started_on' => null,
                'metadata' => [
                    'legacy_number_of_employees' => null,
                    'legacy_category_hash' => null,
                ],
            ],
            'reconciliation' => null,
            'line_of_business' => null,
            'status' => LegacyMappingProposalStatus::Blocked,
            'reasons' => [$reason],
            'category_hash' => null,
            'capital_cents' => null,
            'gross_sales_cents' => null,
        ];
    }

    /** @param array<string, mixed> $line
     * @param  list<string>  $reasons
     */
    private function grossMoney(array $line, array &$reasons): ?int
    {
        $gross = $this->money($line['grossSales'] ?? null);
        $annual = $this->money($line['businessAnnualRevenue'] ?? null);

        if ($gross !== null && $annual !== null && $gross !== $annual) {
            $reasons[] = 'gross_sales_and_legacy_revenue_conflict';

            return null;
        }

        return $gross ?? $annual;
    }

    private function money(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $normalized = str_replace([',', ' '], '', (string) $value);

        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized) !== 1) {
            return null;
        }

        [$whole, $decimal] = array_pad(explode('.', $normalized, 2), 2, '');
        $decimal = str_pad($decimal, 2, '0');

        if (strlen($whole) > 16) {
            return null;
        }

        $cents = ((int) $whole * 100) + (int) $decimal;

        return $cents >= 0 ? $cents : null;
    }

    /** @param list<string> $reasons */
    private function proposalStatus(bool $blocked, array $reasons): LegacyMappingProposalStatus
    {
        if ($blocked) {
            return LegacyMappingProposalStatus::Blocked;
        }

        return $reasons === [] ? LegacyMappingProposalStatus::Ready : LegacyMappingProposalStatus::ReviewRequired;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->squish()->lower()->toString();
    }

    private function string(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }
}
