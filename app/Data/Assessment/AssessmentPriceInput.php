<?php

namespace App\Data\Assessment;

use Spatie\LaravelData\Data;

final class AssessmentPriceInput extends Data
{
    public const Schema = 'bpls.assessment-price-input.v1';

    /**
     * @param  list<AssessmentPriceComponentInput>  $components
     * @param  list<AssessmentPriceModifierInput>  $modifiers
     * @param  list<AssessmentTaxInput>  $taxes
     */
    public function __construct(
        public readonly string $schema_version,
        public readonly string $currency,
        public readonly AssessmentContextData $assessment_context,
        public readonly array $components,
        public readonly array $modifiers,
        public readonly array $taxes,
        public readonly string $composition_policy_version,
    ) {}
}
