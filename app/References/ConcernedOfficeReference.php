<?php

namespace App\References;

use UnexpectedValueException;

final class ConcernedOfficeReference
{
    /** @return list<array{code: string, label: string, fee_rule_codes?: list<string>}> */
    public function items(): array
    {
        $configured = config('ipil_references.concerned_offices.items', []);
        if (! is_array($configured)) {
            throw new UnexpectedValueException('The concerned-office reference items must be an array.');
        }

        $items = [];
        foreach ($configured as $item) {
            if (! is_array($item)
                || ! is_string($item['code'] ?? null)
                || ! is_string($item['label'] ?? null)
                || $item['code'] === ''
                || $item['label'] === '') {
                throw new UnexpectedValueException('Each concerned-office reference item requires a code and label.');
            }

            $feeRuleCodes = $item['fee_rule_codes'] ?? null;
            if ($feeRuleCodes !== null && ! is_array($feeRuleCodes)) {
                throw new UnexpectedValueException('Concerned-office fee rule codes must be strings.');
            }
            $normalizedFeeRuleCodes = [];
            foreach ($feeRuleCodes ?? [] as $feeRuleCode) {
                if (! is_string($feeRuleCode)) {
                    throw new UnexpectedValueException('Concerned-office fee rule codes must be strings.');
                }
                $normalizedFeeRuleCodes[] = $feeRuleCode;
            }

            $items[] = $feeRuleCodes === null
                ? ['code' => $item['code'], 'label' => $item['label']]
                : ['code' => $item['code'], 'label' => $item['label'], 'fee_rule_codes' => $normalizedFeeRuleCodes];
        }

        return $items;
    }

    /** @return array{schema_version: string, production_catalog_status: string} */
    public function provenance(): array
    {
        return [
            'schema_version' => (string) config('ipil_references.concerned_offices.schema_version'),
            'production_catalog_status' => (string) config('ipil_references.concerned_offices.production_catalog_status'),
        ];
    }
}
