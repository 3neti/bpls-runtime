<?php

namespace App\Assessment\Price;

use App\Data\Assessment\AssessmentTaxInput;
use Brick\Money\Money;

final readonly class PriceTax
{
    public function __construct(
        public AssessmentTaxInput $input,
        public Money $money,
    ) {}

    public static function fromInput(AssessmentTaxInput $input): self
    {
        return new self($input, Money::ofMinor($input->amount_minor, $input->currency));
    }
}
