<?php

namespace App\Assessment\Price;

use App\Data\Assessment\AssessmentPriceModifierInput;
use Brick\Money\Money;

final readonly class PriceModifier
{
    public function __construct(
        public AssessmentPriceModifierInput $input,
        public Money $money,
    ) {}

    public static function fromInput(AssessmentPriceModifierInput $input): self
    {
        return new self($input, Money::ofMinor($input->amount_minor, $input->currency));
    }
}
