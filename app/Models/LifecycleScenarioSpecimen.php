<?php

namespace App\Models;

use Database\Factories\LifecycleScenarioSpecimenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $permit_application_id
 * @property string $semantic_result_hash
 * @property array<string, mixed> $owned_resource_manifest
 */
#[Fillable(['scenario_id', 'scenario_revision', 'permit_application_id', 'semantic_result_hash', 'owned_resource_manifest'])]
class LifecycleScenarioSpecimen extends Model
{
    /** @use HasFactory<LifecycleScenarioSpecimenFactory> */
    use HasFactory;

    /** @return BelongsTo<PermitApplication, $this> */
    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['owned_resource_manifest' => 'array'];
    }
}
