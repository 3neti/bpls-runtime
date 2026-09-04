<?php

namespace App\Assessment\Price;

use App\Data\Assessment\AssessmentPriceComponentInput;
use Brick\Money\Money;

final readonly class PriceComponent
{
    public function __construct(
        public AssessmentPriceComponentInput $input,
        public Money $money,
    ) {}

    public static function fromInput(AssessmentPriceComponentInput $input): self
    {
        return new self($input, Money::ofMinor($input->amount_minor, $input->currency));
    }
}
