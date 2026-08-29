<?php

namespace App\Data\Evaluation;

use Spatie\LaravelData\Data;

/**
 * What the applicant originally declared for one line of business. This
 * remains visible even after the Municipality records a correction or an
 * additional activity — the product must never hide the original
 * declaration (see: declaration vs. municipal determination).
 */
class EvaluationDeclaredLineData extends Data
{
    public function __construct(
        public readonly int $line_of_business_id,
        public readonly ?string $line_of_business_name,
        public readonly ?int $declared_gross_sales_cents,
        public readonly ?int $capital_investment_cents,
        public readonly ?int $quantity,
    ) {}
}
