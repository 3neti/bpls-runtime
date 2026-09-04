<?php

namespace App\Assessment\Price;

final readonly class PriceReport
{
    public const Schema = 'bpls.price-report.v1';

    public function __construct(private ResolvedPrice $resolvedPrice) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema_version' => self::Schema,
            'currency' => $this->resolvedPrice->currency,
            'groups' => $this->resolvedPrice->subtotals,
            'components' => $this->resolvedPrice->components,
            'modifiers' => $this->resolvedPrice->modifiers,
            'taxes' => $this->resolvedPrice->taxes,
            'total' => [
                'currency' => $this->resolvedPrice->currency,
                'minor' => $this->resolvedPrice->totalMinor(),
            ],
            'explanation' => collect($this->resolvedPrice->components)
                ->map(fn (array $component): array => [
                    'exact_once_key' => $component['exact_once_key'],
                    'label' => $component['label'],
                    'scheduled_minor' => $component['scheduled_minor'],
                    'resolved_minor' => $component['resolved_minor'],
                ])->values()->all(),
            'provenance' => collect($this->resolvedPrice->components)
                ->map(fn (array $component): array => $component['source'])
                ->values()->all(),
        ];
    }
}
