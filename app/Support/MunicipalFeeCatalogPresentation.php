<?php

namespace App\Support;

use App\Enums\FeeDeterminationChannel;
use App\Enums\FeeRuleCalculationType;
use App\Models\FeeRule;
use Illuminate\Support\Str;

final class MunicipalFeeCatalogPresentation
{
    /** @var list<string> */
    private const ACRONYMS = ['BPO', 'CTC', 'DEC', 'DNEC', 'ID', 'LPG', 'MEC', 'MNEC', 'MPDC', 'MPDO', 'NEC', 'REC', 'RNEC', 'RTW', 'WEC', 'WNEC'];

    /** @var list<string> */
    private const LOWERCASE_WORDS = ['and', 'as', 'at', 'by', 'for', 'from', 'in', 'of', 'on', 'or', 'the', 'to', 'with'];

    public function canonicalName(string $sourceName): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($sourceName)) ?? trim($sourceName);
        $displayName = mb_convert_case(mb_strtolower($normalized), MB_CASE_TITLE, 'UTF-8');

        foreach (self::LOWERCASE_WORDS as $word) {
            $displayName = preg_replace("/\\b{$word}\\b/ui", $word, $displayName) ?? $displayName;
        }
        foreach (self::ACRONYMS as $acronym) {
            $displayName = preg_replace("/\\b{$acronym}\\b/ui", $acronym, $displayName) ?? $displayName;
        }

        return ucfirst($displayName);
    }

    /** @return list<string> */
    public function appliesTo(FeeRule $feeRule): array
    {
        if ($feeRule->lineOfBusinesses->isEmpty()) {
            return ['Application-wide'];
        }

        $divisionName = $feeRule->businessDivision?->name;

        return array_values($feeRule->lineOfBusinesses
            ->map(function ($line) use ($divisionName): string {
                $lineName = $this->canonicalName($line->name);

                return $divisionName !== null && Str::lower($lineName) === Str::lower($divisionName)
                    ? "All {$divisionName}"
                    : $lineName;
            })
            ->unique()
            ->values()
            ->all());
    }

    /** @return array{value: string, basis: string} */
    public function amountAndBasis(FeeRule $feeRule): array
    {
        if (data_get($feeRule->metadata, 'catalog_status') === 'incomplete') {
            return ['value' => 'Needs fee basis', 'basis' => ''];
        }

        if ($feeRule->calculation_type === FeeRuleCalculationType::Fixed && $feeRule->amount_cents > 0) {
            return ['value' => $this->money($feeRule->amount_cents), 'basis' => 'Fixed amount'];
        }

        if ($feeRule->calculation_type === FeeRuleCalculationType::Range && $feeRule->ranges->isNotEmpty()) {
            $minimum = (int) $feeRule->ranges->min('amount_cents');
            $maximum = (int) $feeRule->ranges->max('amount_cents');

            return [
                'value' => $minimum === $maximum ? $this->money($minimum) : $this->money($minimum).'–'.$this->money($maximum),
                'basis' => match ($feeRule->basis) {
                    'declared_gross_sales' => 'Based on gross sales',
                    'capital_investment' => 'Based on capital investment',
                    default => 'Tiered fee',
                },
            ];
        }

        if ($feeRule->calculation_type === FeeRuleCalculationType::Formula) {
            $formula = (string) (data_get($feeRule->metadata, 'formula') ?? data_get($feeRule->metadata, 'legacy_formula') ?? '');

            return ['value' => $this->humanFormula($formula), 'basis' => 'Formula'];
        }

        return ['value' => $this->manualAmountLabel($feeRule->determination_channel), 'basis' => ''];
    }

    private function humanFormula(string $formula): string
    {
        if (preg_match('/^numberOfEmployees\s*\*\s*(\d+(?:\.\d+)?)$/i', trim($formula), $matches) === 1) {
            return $this->money((int) round(((float) $matches[1]) * 100)).' × employee';
        }

        if (preg_match('/^grossSales((?:\s*\*\s*\.?\d+(?:\.\d+)?)*)$/i', trim($formula), $matches) === 1) {
            preg_match_all('/\*\s*(\.?\d+(?:\.\d+)?)/', $matches[1], $factors);
            $rate = collect($factors[1])->reduce(fn (float $product, string $factor): float => $product * (float) $factor, 1.0);
            $percentage = rtrim(rtrim(number_format($rate * 100, 4, '.', ''), '0'), '.');

            return "{$percentage}% of gross sales";
        }

        return $formula === ''
            ? 'Formula not recorded'
            : Str::of($formula)->replaceMatches('/([a-z])([A-Z])/', '$1 $2')->lower()->toString();
    }

    private function manualAmountLabel(FeeDeterminationChannel $channel): string
    {
        return match ($channel) {
            FeeDeterminationChannel::ConcernedOfficePaymentOrder => 'Set during office review',
            FeeDeterminationChannel::TreasuryLineOfBusiness => 'Set during Treasury classification',
            FeeDeterminationChannel::AutomaticAssessment => 'Calculated during assessment',
            FeeDeterminationChannel::ReferenceOnly => 'Needs fee basis',
        };
    }

    private function money(int $amountCents): string
    {
        return '₱'.number_format($amountCents / 100, 2);
    }
}
