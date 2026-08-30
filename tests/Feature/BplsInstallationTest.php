<?php

use App\Actions\BuildMunicipalPriceList;
use App\Actions\InstallBplsBaseline;
use App\Enums\UserPermission;
use App\Models\BusinessOwner;
use App\Models\FeeRule;
use App\Models\InstitutionalPosition;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

test('bpls install establishes a coherent zero-transaction institutional baseline idempotently', function () {
    Storage::fake('local');
    config()->set('bpls_installation.commissioning_administrator.email', 'commissioning.admin@example.test');

    $first = app(InstallBplsBaseline::class)->handle();
    $second = app(InstallBplsBaseline::class)->handle();

    $inspectionRule = FeeRule::query()->with('currentReconciliation')->where('code', 'MRC-3A-04-BUSINESS-INSPECTION')->sole();
    $admin = User::query()->where('email', 'commissioning.admin@example.test')->sole();
    $adminRole = Role::query()->where('code', 'admin')->sole();

    expect($first['integrity'])->toBe(['pass' => true, 'issues' => []])
        ->and($first['zero_state']['is_empty'])->toBeTrue()
        ->and(collect($first['zero_state']['counts'])->every(fn (int $count): bool => $count === 0))->toBeTrue()
        ->and($first['price_list']['in_force'])->toHaveCount(1)
        ->and($first['price_list']['in_force'][0])->toMatchArray([
            'code' => 'MRC-3A-04-BUSINESS-INSPECTION',
            'amount_cents' => 35_000,
            'execution_status' => 'executable',
            'used_by_assessment' => true,
        ])
        ->and($first['price_list']['recorded_confirmation_required'])->toHaveCount(3)
        ->and($first['price_list']['synthetic_uat_exact_published_count'])->toBe(0)
        ->and($first['price_list']['synthetic_uat_fee_rule_count'])->toBe(0)
        ->and($first['price_list']['assessment_parity'])->toBe(['pass' => true, 'new' => true, 'renewal' => true])
        ->and($inspectionRule->amount_cents)->toBe(35_000)
        ->and($inspectionRule->metadata['price_list_source_classification'])->toBe('accepted_municipal_authority')
        ->and($inspectionRule->currentReconciliation?->execution_status->value)->toBe('executable')
        ->and(Role::query()->count())->toBe(15)
        ->and(Permission::query()->count())->toBe(count(UserPermission::cases()))
        ->and(InstitutionalPosition::query()->count())->toBe(13)
        ->and($admin->role_id)->toBe($adminRole->id)
        ->and($adminRole->name)->toBe('BPLS Super User')
        ->and(InstitutionalPosition::query()->where('code', 'super_user')->exists())->toBeFalse()
        ->and($first['commissioning_administrator']['provisioning_status'])->toBe('linked_password_reset_required')
        ->and($second['fingerprints'])->toBe($first['fingerprints'])
        ->and($second['zero_state'])->toBe($first['zero_state'])
        ->and(User::query()->where('email', 'commissioning.admin@example.test')->count())->toBe(1)
        ->and(FeeRule::query()->where('code', 'MRC-3A-04-BUSINESS-INSPECTION')->count())->toBe(1)
        ->and(Storage::disk('local')->exists('private/bpls-installation/manifest.json'))->toBeTrue();

    $this->artisan('bpls:install', ['--check' => true])->assertSuccessful();
});

test('bpls install check is strictly read only', function () {
    Storage::fake('local');
    Artisan::call('bpls:install');
    $before = databaseSnapshot();
    $manifestBefore = Storage::disk('local')->get('private/bpls-installation/manifest.json');

    expect(Artisan::call('bpls:install', ['--check' => true, '--json' => true]))->toBe(0);
    $check = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($check['integrity']['pass'])->toBeTrue()
        ->and(databaseSnapshot())->toBe($before)
        ->and(Storage::disk('local')->get('private/bpls-installation/manifest.json'))->toBe($manifestBefore);
});

test('bpls install never removes existing local transaction data', function () {
    Storage::fake('local');
    Artisan::call('bpls:install');
    $inspectionRuleId = FeeRule::query()->where('code', 'MRC-3A-04-BUSINESS-INSPECTION')->sole()->id;
    $existingOwner = BusinessOwner::factory()->create(['name' => 'Existing local owner']);

    expect(Artisan::call('bpls:install', ['--json' => true]))->toBe(0)
        ->and($existingOwner->fresh()?->name)->toBe('Existing local owner')
        ->and(FeeRule::query()->where('code', 'MRC-3A-04-BUSINESS-INSPECTION')->sole()->id)->toBe($inspectionRuleId);
});

test('public Price List publishes governed pricing and excludes every Scenario 01 amount', function () {
    Artisan::call('bpls:install');
    $priceList = app(BuildMunicipalPriceList::class)->handle();
    $charges = collect($priceList['services'])->flatMap(fn (array $service): array => $service['pricing']['confirmed_charges']);

    expect($charges)->toHaveCount(1)
        ->and($charges->first()['traceability']['rule_code'])->toBe('MRC-3A-04-BUSINESS-INSPECTION')
        ->and($charges->first()['amount_cents'])->toBe(35_000)
        ->and(FeeRule::query()->whereIn('amount_cents', [24_000, 9_000, 31_000, 9_500, 6_500, 7_000])->count())->toBe(0)
        ->and(FeeRule::query()->get()->contains(fn (FeeRule $rule): bool => data_get($rule->metadata, 'semantic_classification') === 'provisional_uat'))->toBeFalse();
});

test('institutional roles preserve separation of duties and authority seats remain unassigned', function () {
    Artisan::call('bpls:install');
    Role::query()->where('code', 'treasury')->sole()->permissions()->attach(
        Permission::query()->where('code', 'assessments.approve')->sole(),
    );
    Artisan::call('bpls:install');
    $roles = Role::query()->with('permissions')->get()->keyBy('code');

    expect($roles['treasury']->permissions->pluck('code'))->toContain('business_permit_evaluations.counter_check')
        ->not->toContain('assessments.approve')
        ->and($roles['assessment_officer']->permissions->pluck('code'))->toContain('permit_applications.assess')
        ->not->toContain('assessments.approve')
        ->and($roles['municipal_treasurer']->permissions->pluck('code'))->toContain('assessments.approve')
        ->not->toContain('business_permit_evaluations.counter_check')
        ->and($roles['cashier']->permissions->pluck('code'))->toContain('receipts.issue')
        ->not->toContain('assessments.approve')
        ->and(InstitutionalPosition::query()->where('assignment_status', 'unassigned')->count())->toBe(13)
        ->and(InstitutionalPosition::query()->where('code', 'municipal_treasurer')->value('authority_classification'))->toBe('statutory_assessment_approval');
});

/** @return array<string, list<array<string, mixed>>> */
function databaseSnapshot(): array
{
    return collect(Schema::getTableListing())
        ->sort()
        ->mapWithKeys(function (string $table): array {
            $rows = DB::table($table)->get()->map(fn (object $row): array => (array) $row)->all();

            return [$table => $rows];
        })
        ->all();
}
