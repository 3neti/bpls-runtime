<?php

namespace App\Data\Evaluation;

use Spatie\LaravelData\Data;

/**
 * A charge projected from the governed pricing path (the same one used by
 * Assessment). This is pricing basis / system proposal information — it
 * is never Evaluator-invented pricing, and Evaluator does not duplicate
 * office-resolved charges here.
 */
class EvaluationProjectedChargeData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly int $fee_rule_id,
        public readonly string $code,
        public readonly string $name,
        public readonly int $amount_cents,
        public readonly string $basis,
        public readonly ?int $basis_amount_cents,
        public readonly ?string $legal_basis,
        public readonly string $source_classification,
    ) {}
}
