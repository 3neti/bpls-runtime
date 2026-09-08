<?php

namespace Database\Seeders;

use App\Enums\FeeDeterminationChannel;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\References\NelsonTreasuryLobFeeCatalog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NelsonTreasuryLobFeeCatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    public function __construct(private readonly NelsonTreasuryLobFeeCatalog $catalog) {}

    public function run(): void
    {
        $catalog = $this->catalog->load();
        DB::transaction(function () use ($catalog): void {
            foreach ($catalog['lines_of_business'] as $configuredLine) {
                $line = LineOfBusiness::query()->updateOrCreate(
                    ['code' => $configuredLine['code']],
                    [
                        'name' => $configuredLine['name'],
                        'major_category' => $configuredLine['major_category'] ?? null,
                        'is_active' => true,
                        'metadata' => $this->metadata($catalog),
                    ],
                );
                foreach ($catalog['periods'] as $period) {
                    foreach ($configuredLine['payment_items'] as $item) {
                        $fee = FeeRule::query()
                            ->where('code', $item['code'])
                            ->whereDate('effective_from', $period['effective_from'])
                            ->firstOrNew();
                        $fee->fill([
                            'line_of_business_id' => $line->id,
                            'code' => $item['code'],
                            'effective_from' => $period['effective_from'],
                            'name' => $item['label'],
                            'category' => FeeRuleCategory::Fee,
                            'scope' => FeeRuleScope::LineOfBusiness,
                            'determination_channel' => FeeDeterminationChannel::TreasuryLineOfBusiness,
                            'calculation_type' => FeeRuleCalculationType::Fixed,
                            'basis' => 'none',
                            'amount_cents' => $item['default_amount_minor'],
                            'effective_until' => $period['effective_until'],
                            'is_active' => true,
                            'metadata' => $this->metadata($catalog),
                        ])->save();
                    }
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>
     */
    private function metadata(array $catalog): array
    {
        return [
            'source_name' => $catalog['source_name'],
            'schema_version' => $catalog['schema_version'],
            'catalog_version' => $catalog['catalog_version'],
            'catalog_digest_sha256' => $catalog['digest_sha256'],
            'classification' => $catalog['classification'],
            'semantic_classification' => 'synthetic_only',
            'production_authority' => false,
            'production_catalog_status' => $catalog['production_catalog_status'],
            'currency' => $catalog['currency'],
            'business_tax_applicability' => 'prohibited_for_new',
            'inspection_in_scope' => false,
        ];
    }
}
