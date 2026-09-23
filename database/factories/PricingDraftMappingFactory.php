<?php

namespace Database\Factories;

use App\Models\PricingChargeItem;
use App\Models\PricingDefinitionDraft;
use App\Models\PricingDraftMapping;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PricingDraftMapping> */
class PricingDraftMappingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pricing_definition_draft_id' => PricingDefinitionDraft::factory(),
            'pricing_charge_item_id' => PricingChargeItem::factory(),
            'revision' => 1,
            'revenue_account_id' => null,
            'recorded_by' => User::factory(),
            'evidence_reference' => 'test-only mapping review',
            'evidence_sha256' => hash('sha256', 'test-only review'),
            'rationale' => 'Explicit test mapping; not fiscal authority.',
        ];
    }
}
