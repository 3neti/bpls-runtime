<?php

namespace App\Assessment\Price;

use Brick\Money\Money;

final readonly class ResolvedPrice
{
    /**
     * @param  list<array<string, mixed>>  $components
     * @param  list<array<string, mixed>>  $modifiers
     * @param  list<array<string, mixed>>  $taxes
     * @param  list<array<string, mixed>>  $subtotals
     */
    public function __construct(
        public string $currency,
        public array $components,
        public array $modifiers,
        public array $taxes,
        public array $subtotals,
        public Money $total,
    ) {}

    public function totalMinor(): int
    {
        return $this->total->getMinorAmount()->toInt();
    }
}
