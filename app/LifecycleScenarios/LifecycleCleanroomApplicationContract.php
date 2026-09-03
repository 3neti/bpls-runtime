<?php

namespace App\LifecycleScenarios;

use App\Actions\BuildLifecycleCleanroomRegistryProfile;
use App\Models\PermitApplication;
use LogicException;
use UnexpectedValueException;

class LifecycleCleanroomApplicationContract
{
    public function __construct(
        private readonly NewApplicationHappyPathDefinition $definition,
        private readonly BuildLifecycleCleanroomRegistryProfile $buildRegistryProfile,
    ) {}

    public function blocker(?PermitApplication $application): ?string
    {
        if (! $application instanceof PermitApplication) {
            return null;
        }

        try {
            $this->profile($application);

            return null;
        } catch (UnexpectedValueException $exception) {
            return $exception->getMessage().' The laboratory will not invent missing activities, office ownership, or charges.';
        }
    }

    public function assertCompatible(PermitApplication $application): void
    {
        $blocker = $this->blocker($application);
        if (is_string($blocker)) {
            throw new LogicException($blocker);
        }
    }

    /** @return array<string, mixed> */
    public function profile(PermitApplication $application): array
    {
        $registryProfile = $this->buildRegistryProfile->handle($application);
        if (is_array($registryProfile)) {
            return $registryProfile;
        }

        $requiredCodes = collect($this->definition->linesOfBusiness())->pluck('code')->sort()->values()->all();
        $actualCodes = $application->lines()
            ->with('lineOfBusiness:id,code')
            ->get()
            ->pluck('lineOfBusiness.code')
            ->filter(fn (mixed $code): bool => is_string($code))
            ->sort()
            ->values()
            ->all();
        if ($actualCodes !== $requiredCodes) {
            throw new UnexpectedValueException('This lodged declaration has no complete certified or source-backed laboratory assessment profile.');
        }

        return [
            'kind' => 'certified_two_year',
            'profile_version' => NewApplicationHappyPathDefinition::Revision,
            'scope' => 'two_year',
            'source_reference' => NewApplicationHappyPathDefinition::Id,
            'expected_total_amount_cents' => NewApplicationHappyPathDefinition::ExpectedGrandTotalCents,
            'responsibilities' => $this->definition->responsibilities(),
            'statement' => 'The fixed certified two-year product-laboratory profile remains unchanged.',
        ];
    }
}
