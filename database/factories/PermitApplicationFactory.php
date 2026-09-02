<?php

namespace Database\Factories;

use App\Actions\PermitApplicationStatusMutation;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Models\Business;
use App\Models\PermitApplication;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @extends Factory<PermitApplication>
 */
class PermitApplicationFactory extends Factory
{
    private ?PermitApplicationStatus $explicitStatus = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'application_number' => fake()->unique()->numerify('APP-2026-#####'),
            'type' => PermitApplicationType::New,
            'status' => PermitApplicationStatus::Draft,
            'application_year' => 2026,
            'submitted_at' => now(),
            'metadata' => [],
        ];
    }

    public function withStatus(PermitApplicationStatus $status): static
    {
        $factory = $this->state(['status' => $status]);
        $factory->explicitStatus = $status;

        return $factory;
    }

    /**
     * @param  (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed>  $attributes
     * @return Collection<int, PermitApplication>|PermitApplication
     */
    public function create($attributes = [], ?Model $parent = null): Collection|PermitApplication
    {
        if (! $this->explicitStatus instanceof PermitApplicationStatus) {
            return parent::create($attributes, $parent);
        }

        if (is_callable($attributes)) {
            return PermitApplicationStatusMutation::forFactoryFixture(
                fn (): Collection|PermitApplication => parent::create($attributes, $parent),
            );
        }

        if (array_key_exists('status', $attributes)) {
            $attributeStatus = $attributes['status'] instanceof PermitApplicationStatus
                ? $attributes['status']
                : PermitApplicationStatus::tryFrom((string) $attributes['status']);

            if ($attributeStatus !== $this->explicitStatus) {
                throw new LogicException('The PermitApplication factory status must match its explicit fixture privilege.');
            }
        }

        return PermitApplicationStatusMutation::forFactoryFixture(
            fn (): Collection|PermitApplication => parent::create($attributes, $parent),
        );
    }

    /** @param array<string, mixed> $arguments */
    protected function newInstance(array $arguments = []): static
    {
        $factory = parent::newInstance($arguments);
        $factory->explicitStatus = $this->explicitStatus;

        return $factory;
    }
}
