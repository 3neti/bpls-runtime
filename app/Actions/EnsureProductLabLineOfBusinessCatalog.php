<?php

namespace App\Actions;

use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\Models\LineOfBusiness;
use LogicException;

class EnsureProductLabLineOfBusinessCatalog
{
    public function __construct(private readonly NewApplicationHappyPathDefinition $definition) {}

    /** @return array<string, LineOfBusiness> */
    public function handle(): array
    {
        return collect($this->definition->linesOfBusiness())->mapWithKeys(function (array $definition): array {
            $lineOfBusiness = LineOfBusiness::query()->firstOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'major_category' => $definition['major_category'],
                    'is_active' => true,
                    'metadata' => [
                        'scenario_id' => 'product-lab-chronology',
                        'semantic_classification' => 'provisional_uat',
                        'production_liability' => false,
                    ],
                ],
            );
            if (data_get($lineOfBusiness->metadata, 'scenario_id') !== 'product-lab-chronology') {
                throw new LogicException("LOB code [{$definition['code']}] is occupied by non-scenario data.");
            }

            return [$definition['code'] => $lineOfBusiness];
        })->all();
    }
}
