<?php

namespace App\Assessment\Price;

use App\Data\Assessment\AssessmentPriceComponentInput;
use App\Data\Assessment\AssessmentPriceInput;
use Brick\Money\Money;
use InvalidArgumentException;
use LogicException;

final readonly class Price
{
    /**
     * @param  list<PriceComponent>  $components
     * @param  list<PriceModifier>  $modifiers
     * @param  list<PriceTax>  $taxes
     */
    private function __construct(
        private string $currency,
        private array $components,
        private array $modifiers,
        private array $taxes,
    ) {
        $this->assertInvariants();
    }

    public static function fromMoney(Money $money, string $label = 'Amount'): self
    {
        $currency = $money->getCurrency()->getCurrencyCode();
        $minor = $money->getMinorAmount()->toInt();
        $key = hash('sha256', "{$currency}:{$minor}:{$label}");

        return self::fromComponent(new AssessmentPriceComponentInput(
            key: $key,
            type: 'amount',
            label: $label,
            scope: 'application',
            permit_application_line_id: null,
            line_of_business_id: null,
            line_of_business_name: null,
            responsible_office: null,
            currency: $currency,
            amount_minor: $minor,
            source_type: 'money',
            source_identity: $key,
            source_version: 'transient.v1',
            exact_once_key: "money:{$key}",
            legal_basis: null,
            explanation: ['canonical_money' => "{$currency}:{$minor}"],
        ));
    }

    public static function fromComponent(AssessmentPriceComponentInput $component): self
    {
        return new self($component->currency, [PriceComponent::fromInput($component)], [], []);
    }

    public static function fromInput(AssessmentPriceInput $input): self
    {
        return new self(
            $input->currency,
            array_map(PriceComponent::fromInput(...), $input->components),
            array_map(PriceModifier::fromInput(...), $input->modifiers),
            array_map(PriceTax::fromInput(...), $input->taxes),
        );
    }

    /** @return list<PriceComponent> */
    public function components(): array
    {
        return $this->components;
    }

    /** @return list<PriceModifier> */
    public function modifiers(): array
    {
        return $this->modifiers;
    }

    /** @return list<PriceTax> */
    public function taxes(): array
    {
        return $this->taxes;
    }

    public function money(): Money
    {
        return $this->resolve()->total;
    }

    public function report(): PriceReport
    {
        return new PriceReport($this->resolve());
    }

    public function resolve(): ResolvedPrice
    {
        $zero = Money::ofMinor(0, $this->currency);
        $modifiersByTarget = collect($this->modifiers)->groupBy(
            fn (PriceModifier $modifier): string => $modifier->input->target_exact_once_key,
        );

        $resolvedComponents = collect($this->components)->map(function (PriceComponent $component) use ($modifiersByTarget): array {
            $resolved = $component->money;
            $appliedModifiers = $modifiersByTarget->get($component->input->exact_once_key, collect());

            foreach ($appliedModifiers as $modifier) {
                $resolved = $resolved->plus($modifier->money);
            }

            if ($resolved->isNegative()) {
                throw new LogicException("Price component [{$component->input->exact_once_key}] resolved below zero.");
            }

            return [
                ...$component->input->toArray(),
                'scheduled_minor' => $component->input->amount_minor,
                'resolved_minor' => $resolved->getMinorAmount()->toInt(),
                'source' => [
                    'type' => $component->input->source_type,
                    'identity' => $component->input->source_identity,
                    'version' => $component->input->source_version,
                ],
                'applied_modifier_keys' => $appliedModifiers->map(
                    fn (PriceModifier $modifier): string => $modifier->input->key,
                )->values()->all(),
            ];
        })->values();

        $resolvedTaxes = collect($this->taxes)->map(fn (PriceTax $tax): array => [
            ...$tax->input->toArray(),
            'resolved_minor' => $tax->money->getMinorAmount()->toInt(),
        ])->values();

        $total = $resolvedComponents->reduce(
            fn (Money $carry, array $component): Money => $carry->plus(Money::ofMinor($component['resolved_minor'], $this->currency)),
            $zero,
        );
        $total = $resolvedTaxes->reduce(
            fn (Money $carry, array $tax): Money => $carry->plus(Money::ofMinor($tax['resolved_minor'], $this->currency)),
            $total,
        );

        $subtotals = $resolvedComponents
            ->groupBy(fn (array $component): string => $component['scope'] === 'application'
                ? 'application'
                : 'line_of_business:'.$component['line_of_business_id'])
            ->map(fn ($components, string $key): array => [
                'key' => $key,
                'label' => $key === 'application'
                    ? 'Application-wide'
                    : (string) ($components->first()['line_of_business_name'] ?? 'Line of Business'),
                'currency' => $this->currency,
                'minor' => (int) $components->sum('resolved_minor'),
            ])->values()->all();

        return new ResolvedPrice(
            currency: $this->currency,
            components: array_values($resolvedComponents->all()),
            modifiers: array_values(collect($this->modifiers)->map(fn (PriceModifier $modifier): array => $modifier->input->toArray())->all()),
            taxes: array_values($resolvedTaxes->all()),
            subtotals: array_values($subtotals),
            total: $total,
        );
    }

    private function assertInvariants(): void
    {
        if (! preg_match('/^[A-Z]{3}$/', $this->currency)) {
            throw new InvalidArgumentException('Price currency must be an uppercase ISO 4217 code.');
        }

        $allCurrencies = collect([
            ...array_map(fn (PriceComponent $component): string => $component->money->getCurrency()->getCurrencyCode(), $this->components),
            ...array_map(fn (PriceModifier $modifier): string => $modifier->money->getCurrency()->getCurrencyCode(), $this->modifiers),
            ...array_map(fn (PriceTax $tax): string => $tax->money->getCurrency()->getCurrencyCode(), $this->taxes),
        ])->unique();
        if ($allCurrencies->contains(fn (string $currency): bool => $currency !== $this->currency)) {
            throw new LogicException('Mixed currencies are not permitted and implicit foreign exchange is unavailable.');
        }

        $duplicateKeys = collect($this->components)
            ->groupBy(fn (PriceComponent $component): string => $component->input->exact_once_key)
            ->filter(fn ($matches): bool => $matches->count() > 1)
            ->keys();
        if ($duplicateKeys->isNotEmpty()) {
            throw new LogicException('Price components must be exact-once; duplicate keys: '.$duplicateKeys->implode(', ').'.');
        }

        $componentKeys = collect($this->components)->map(
            fn (PriceComponent $component): string => $component->input->exact_once_key,
        );
        foreach ($this->modifiers as $modifier) {
            if ($modifier->input->type !== 'case_override') {
                throw new LogicException("Price modifier type [{$modifier->input->type}] is not commissioned in V1.");
            }
            if (! $componentKeys->contains($modifier->input->target_exact_once_key)) {
                throw new LogicException("Price modifier [{$modifier->input->key}] targets a missing component.");
            }
            if (blank($modifier->input->reason) || blank($modifier->input->authority)) {
                throw new LogicException("Price modifier [{$modifier->input->key}] requires reason and authority provenance.");
            }
        }
    }
}
