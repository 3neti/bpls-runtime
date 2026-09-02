<?php

namespace App\Assessment;

use App\Exceptions\UnsupportedAssessmentPolicy;
use Illuminate\Support\Collection;
use LogicException;

final class AssessmentComposition
{
    /**
     * @param  iterable<AssessmentComponent>  $components
     * @return Collection<int, AssessmentComponent>
     */
    public function ordered(iterable $components): Collection
    {
        $components = collect($components)->values();

        $duplicateKeys = $components
            ->groupBy(fn (AssessmentComponent $component): string => $component->exactOnceKey)
            ->filter(fn (Collection $matches): bool => $matches->count() > 1)
            ->keys();

        if ($duplicateKeys->isNotEmpty()) {
            throw new LogicException('Assessment components must be exact-once; duplicate source keys: '.$duplicateKeys->implode(', ').'.');
        }

        $percentageComponent = $components->first(
            fn (AssessmentComponent $component): bool => $component->percentageBaseKeys !== [],
        );

        if ($percentageComponent instanceof AssessmentComponent) {
            throw new UnsupportedAssessmentPolicy("Percentage component [{$percentageComponent->key}] is blocked until its rate, explicit base, ordering, and rounding policy are commissioned.");
        }

        return $components
            ->sortBy(fn (AssessmentComponent $component): string => sprintf('%010d:%s', $component->orderingPhase, $component->exactOnceKey))
            ->values();
    }

    /** @param iterable<AssessmentComponent> $components */
    public function totalAmountCents(iterable $components): int
    {
        return $this->ordered($components)->sum(
            fn (AssessmentComponent $component): int => $component->amountCents,
        );
    }
}
