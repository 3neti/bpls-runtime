<?php

namespace Database\Seeders;

use App\Enums\FeeDeterminationChannel;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Models\FeeRule;
use App\References\NelsonConcernedOfficeFeeCatalog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NelsonConcernedOfficeFeeCatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    public function __construct(private readonly NelsonConcernedOfficeFeeCatalog $catalog) {}

    public function run(): void
    {
        $catalog = $this->catalog->load();

        DB::transaction(function () use ($catalog): void {
            foreach ($catalog['periods'] as $period) {
                foreach ($catalog['fees'] as $fee) {
                    $existing = FeeRule::query()
                        ->where('code', $fee['code'])
                        ->whereDate('effective_from', $period['effective_from'])
                        ->first();
                    if ($existing instanceof FeeRule
                        && data_get($existing->metadata, 'catalog_version') !== $catalog['catalog_version']) {
                        continue;
                    }

                    $attributes = [
                        'line_of_business_id' => null,
                        'name' => $fee['label'],
                        'category' => FeeRuleCategory::Fee,
                        'scope' => FeeRuleScope::Application,
                        'determination_channel' => FeeDeterminationChannel::ConcernedOfficePaymentOrder,
                        'calculation_type' => FeeRuleCalculationType::Fixed,
                        'basis' => 'none',
                        'amount_cents' => $fee['default_amount_minor'],
                        'rate_basis_points' => null,
                        'effective_until' => $period['effective_until'],
                        'legal_basis' => null,
                        'is_active' => true,
                        'legacy_source_id' => null,
                        'metadata' => [
                            'source_name' => $catalog['source_name'],
                            'schema_version' => $catalog['schema_version'],
                            'catalog_version' => $catalog['catalog_version'],
                            'catalog_digest_sha256' => $catalog['digest_sha256'],
                            'classification' => $catalog['classification'],
                            'semantic_classification' => 'synthetic_only',
                            'production_authority' => $catalog['production_authority'],
                            'production_catalog_status' => $catalog['production_catalog_status'],
                            'currency' => $catalog['currency'],
                            'application_year' => $period['application_year'],
                            'responsible_office_code' => $fee['office_code'],
                            'municipal_account_code' => $fee['account_code'],
                            'source_reference_status' => 'provisional_transcription',
                            'assessment_selection' => 'concerned_office_payment_order_only',
                            'inspection_in_scope' => false,
                        ],
                    ];

                    if ($existing instanceof FeeRule) {
                        $existing->forceFill($attributes)->save();

                        continue;
                    }

                    FeeRule::query()->create([
                        'code' => $fee['code'],
                        'effective_from' => $period['effective_from'],
                        ...$attributes,
                    ]);
                }
            }
        });
    }
}
