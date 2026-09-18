<?php

use App\Actions\BuildBploRoutingTask;
use App\Enums\FeeDeterminationChannel;
use App\Enums\FeeRuleScope;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use Illuminate\Support\Facades\DB;

test('Treasury catalog options preserve direct and pivot fee mappings without rescanning the catalog per option', function (): void {
    $lines = LineOfBusiness::factory()->count(105)->sequence(
        fn ($sequence): array => [
            'code' => sprintf('INDEX-LOB-%03d', $sequence->index + 1),
            'name' => sprintf('Catalog Line %03d', $sequence->index + 1),
        ],
    )->create();
    $target = $lines[101];
    $unrelated = $lines[102];

    $direct = FeeRule::factory()->create([
        'code' => 'INDEX-DIRECT',
        'name' => 'A Direct Fee',
        'scope' => FeeRuleScope::LineOfBusiness,
        'determination_channel' => FeeDeterminationChannel::TreasuryLineOfBusiness,
        'line_of_business_id' => $target->id,
        'amount_cents' => 10_000,
        'effective_from' => '2025-01-01',
    ]);
    $pivot = FeeRule::factory()->create([
        'code' => 'INDEX-PIVOT',
        'name' => 'B Pivot Fee',
        'scope' => FeeRuleScope::LineOfBusiness,
        'determination_channel' => FeeDeterminationChannel::TreasuryLineOfBusiness,
        'amount_cents' => 20_000,
        'effective_from' => '2025-01-01',
    ]);
    $pivot->lineOfBusinesses()->attach($target);
    $both = FeeRule::factory()->create([
        'code' => 'INDEX-BOTH',
        'name' => 'C Direct And Pivot Fee',
        'scope' => FeeRuleScope::LineOfBusiness,
        'determination_channel' => FeeDeterminationChannel::TreasuryLineOfBusiness,
        'line_of_business_id' => $target->id,
        'amount_cents' => 30_000,
        'effective_from' => '2025-01-01',
    ]);
    $both->lineOfBusinesses()->attach($target);
    FeeRule::factory()->create([
        'code' => 'INDEX-UNRELATED',
        'name' => 'D Unrelated Fee',
        'scope' => FeeRuleScope::LineOfBusiness,
        'determination_channel' => FeeDeterminationChannel::TreasuryLineOfBusiness,
        'line_of_business_id' => $unrelated->id,
        'amount_cents' => 40_000,
        'effective_from' => '2025-01-01',
    ]);
    FeeRule::factory()->create([
        'code' => 'INDEX-WRONG-CHANNEL',
        'name' => 'E Wrong Channel Fee',
        'scope' => FeeRuleScope::LineOfBusiness,
        'determination_channel' => FeeDeterminationChannel::ConcernedOfficePaymentOrder,
        'line_of_business_id' => $target->id,
        'amount_cents' => 50_000,
        'effective_from' => '2025-01-01',
    ]);

    $application = PermitApplication::factory()->create([
        'application_year' => 2025,
        'type' => 'new',
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $options = collect(app(BuildBploRoutingTask::class)->handle($application, null)
        ->financial_editor['line_of_business_options']);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $targetIndex = $options->search(fn (array $option): bool => $option['id'] === $target->id);
    $targetItems = collect($options[$targetIndex]['default_items']);
    $emptyItems = collect($options->firstWhere('id', $lines[104]->id)['default_items']);

    expect($options)->toHaveCount(105)
        ->and($targetIndex)->toBeInt()->toBeGreaterThan(100)
        ->and($targetItems->pluck('code')->all())->toBe([
            $direct->code,
            $pivot->code,
            $both->code,
        ])
        ->and($targetItems->where('code', $both->code))->toHaveCount(1)
        ->and($targetItems->pluck('code'))->not->toContain('INDEX-UNRELATED', 'INDEX-WRONG-CHANNEL')
        ->and($emptyItems)->toBeEmpty()
        ->and($queryCount)->toBeLessThan(40);
});
