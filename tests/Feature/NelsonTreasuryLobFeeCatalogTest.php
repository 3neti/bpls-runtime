<?php

use App\Enums\FeeRuleCategory;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\References\NelsonTreasuryLobFeeCatalog;
use Database\Seeders\NelsonTreasuryLobFeeCatalogSeeder;

test('versioned Nelson Treasury preview catalog provides three synthetic LOBs and no Business Tax', function () {
    $catalog = app(NelsonTreasuryLobFeeCatalog::class)->load();
    $this->seed(NelsonTreasuryLobFeeCatalogSeeder::class);
    $this->seed(NelsonTreasuryLobFeeCatalogSeeder::class);

    $lines = LineOfBusiness::query()->where('code', 'like', 'LAB-NELSON-LOB-%')->orderBy('code')->get();
    $fees = FeeRule::query()->where('code', 'like', 'LAB-NELSON-LOB-%-PERMIT')->get();

    expect($catalog['catalog_version'])->toBe('nelson-treasury-lob-preview-v1')
        ->and($catalog['classification'])->toBe('synthetic_preview')
        ->and($catalog['production_authority'])->toBeFalse()
        ->and($catalog['production_catalog_status'])->toBe('awaiting_nelson_source')
        ->and($lines)->toHaveCount(3)
        ->and($fees)->toHaveCount(6)
        ->and($fees->pluck('category')->every(fn (FeeRuleCategory $category): bool => $category === FeeRuleCategory::Fee))->toBeTrue()
        ->and($fees->every(fn (FeeRule $fee): bool => data_get($fee->metadata, 'business_tax_applicability') === 'prohibited_for_new'))->toBeTrue();
});
