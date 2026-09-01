<?php

use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\PermitApplication;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

test('product-lab inspection certifies one identity and the effective-2025 to effective-2026 chronology', function () {
    Storage::fake('local');
    Artisan::call('bpls:install');

    expect(Artisan::call('bpls:lifecycle:run', [
        'scenario' => NewApplicationHappyPathDefinition::Id,
        '--persist' => true,
        '--json' => true,
    ]))->toBe(0)
        ->and(Artisan::call('bpls:lifecycle:run', [
            'scenario' => RenewalHappyPathDefinition::Id,
            '--persist' => true,
            '--json' => true,
        ]))->toBe(0)
        ->and(Artisan::call('bpls:product-lab:inspect', ['--json' => true]))->toBe(0);

    $guide = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $applications = PermitApplication::query()->orderBy('application_year')->get();

    expect(BusinessOwner::query()->count())->toBe(1)
        ->and(Business::query()->count())->toBe(1)
        ->and($applications)->toHaveCount(2)
        ->and($applications->pluck('business_id')->unique())->toHaveCount(1)
        ->and($applications->pluck('application_year')->all())->toBe([2025, 2026])
        ->and(data_get($applications->last()->metadata, 'lifecycle_scenario.predecessor_permit_application_id'))->toBe($applications->first()->id)
        ->and($guide['institution']['price_list'])->toBe('PASS')
        ->and($guide['institution']['synthetic_prices_published'])->toBe(0)
        ->and($guide['inventory'])->toBe([
            'business_owners' => 1,
            'businesses' => 1,
            'permit_applications' => 2,
        ])
        ->and(data_get($guide, 'applications.0.year'))->toBe(2025)
        ->and(data_get($guide, 'applications.1.year'))->toBe(2026)
        ->and(data_get($guide, 'applications.1.amount_due_cents'))->toBe(122_000)
        ->and($guide['links'])->toHaveKeys([
            'stakeholder_preview',
            'citizen_profile',
            'business',
            'new_application',
            'renewal_application',
            'bplo',
            'concerned_offices',
            'assessment_officer_assessment',
            'treasury',
            'municipal_treasurer',
            'payable',
            'citizen_services_and_fees',
        ]);
});

test('the local product-lab wrapper is executable and preserves the required hard-gated order', function () {
    $script = base_path('bin/bpls-product-lab');
    $contents = file_get_contents($script);

    expect(is_executable($script))->toBeTrue()
        ->and($contents)->toContain('set -euo pipefail')
        ->and($contents)->toContain('bpls:product-lab:preflight')
        ->and($contents)->toContain('migrate:fresh --force')
        ->and(strpos($contents, 'new-application-happy-path --persist'))->toBeLessThan(strpos($contents, 'renewal-happy-path --persist'))
        ->and($contents)->toContain('bpls:product-lab:inspect');
});
