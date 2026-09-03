<?php

namespace App\LifecycleScenarios;

use App\Models\PermitApplication;
use LogicException;

class LifecycleCleanroomApplicationContract
{
    public function __construct(
        private readonly NewApplicationHappyPathDefinition $definition,
    ) {}

    public function blocker(?PermitApplication $application): ?string
    {
        if (! $application instanceof PermitApplication) {
            return null;
        }

        $requiredCodes = collect($this->definition->linesOfBusiness())
            ->pluck('code')
            ->sort()
            ->values()
            ->all();
        $actualCodes = $application->lines()
            ->with('lineOfBusiness:id,code')
            ->get()
            ->pluck('lineOfBusiness.code')
            ->filter(fn (mixed $code): bool => is_string($code))
            ->sort()
            ->values()
            ->all();

        if ($actualCodes === $requiredCodes) {
            return null;
        }

        return 'This lodged declaration does not match the certified cleanroom activity set of Retail Trading and Food Service. Submitted activities are immutable, so the laboratory will not add a missing activity or invent its provisional charges. Close this run and start a new cleanroom with the prepared activity rows. Use standalone application testing for real registry specimens until their responsibility profiles are commissioned.';
    }

    public function assertCompatible(PermitApplication $application): void
    {
        $blocker = $this->blocker($application);
        if (is_string($blocker)) {
            throw new LogicException($blocker);
        }
    }
}
