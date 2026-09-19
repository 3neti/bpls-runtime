<?php

use App\Actions\ProvisionLifecycleLaboratoryActors;
use App\LifecycleScenarios\TreasuryBrowserFixtures;
use App\Models\FeeRule;
use App\Models\PermitApplication;
use App\Models\User;
use Database\Seeders\MunicipalFeeCatalogSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config([
        'app.url' => 'http://bpls-runtime.test',
        'stakeholder_preview.mode' => true,
        'stakeholder_preview.pii_mode' => 'synthetic_only',
        'stakeholder_preview.production_migration_enabled' => false,
        'stakeholder_preview.production_integrations' => 'disabled',
        'treasury_enterprise.provisional_uat_enabled' => true,
        'treasury_enterprise.provisional_uat_context' => 'gate10_local',
        'filesystems.signature_evidence_disk' => 'local',
        'bpls_installation.seed_laboratory_actors' => true,
    ]);
    Storage::fake('local');
    Artisan::call('bpls:install');
    app(ProvisionLifecycleLaboratoryActors::class)->handle();
    $this->seed(MunicipalFeeCatalogSeeder::class);
});

test('dedicated Treasury fixtures use canonical precursor actions and preserve the existing estate', function (): void {
    $protected = PermitApplication::factory()->create();
    $before = $protected->fresh()->getRawOriginal();
    $users = User::query()->orderBy('id')->get()->map->getRawOriginal()->all();
    $catalog = FeeRule::query()->orderBy('id')->get()->toJson();
    $config = config('treasury_enterprise');
    $builder = app(TreasuryBrowserFixtures::class);
    foreach (TreasuryBrowserFixtures::Keys as $key) {
        $manifest = $builder->prepare($key);
        expect($manifest['payment_order_total_cents'])->toBe(305000)
            ->and($manifest['payment_order_ids'])->toHaveCount(4)
            ->and($manifest['treasury_assignments'])->toBe(0)
            ->and($manifest['assessments'])->toBe(0)
            ->and($manifest['payment_schedules'])->toBe(0)
            ->and($manifest['declaration_sha256'])->toHaveLength(64)
            ->and($manifest['tracking_reference'])->toStartWith('SUB-')
            ->and($manifest['enterprise_schedule']['bands'])->toHaveCount(5)
            ->and($builder->prepare($key))->toBe($manifest);
    }
    expect($protected->fresh()->getRawOriginal())->toBe($before)
        ->and(FeeRule::query()->orderBy('id')->get()->toJson())->toBe($catalog)
        ->and(config('treasury_enterprise'))->toBe($config)
        ->and(User::query()->whereIn('id', array_column($users, 'id'))->orderBy('id')->get()->map->getRawOriginal()->all())->toBe($users);
});

test('fixture command needs explicit permission and rejects Cloud without mutation', function (): void {
    $count = PermitApplication::count();
    $this->artisan('bpls:treasury:prepare-browser-fixture', ['key' => 'T2026-NO-LOB'])->assertFailed();
    config(['app.url' => 'https://example.laravel.cloud']);
    $this->artisan('bpls:treasury:prepare-browser-fixture', ['key' => 'T2026-NO-LOB', '--confirm-local-synthetic' => true])->assertFailed();
    expect(PermitApplication::count())->toBe($count);
});

test('fixture preparation cannot silently broaden to unsupported policy contexts', function (): void {
    $count = PermitApplication::count();
    expect(fn () => app(TreasuryBrowserFixtures::class)->prepare('T2026-NO-OPERATIVE-SCHEDULE'))->toThrow(RuntimeException::class, 'Unsupported');
    expect(PermitApplication::count())->toBe($count);
});

test('a changed fixture is rejected rather than overwritten on reuse', function (string $change): void {
    $builder = app(TreasuryBrowserFixtures::class);
    $manifest = $builder->prepare('T2026-NO-LOB');
    $application = PermitApplication::findOrFail($manifest['application_id']);
    if ($change === 'amount') {
        // Deliberate corruption in the isolated test database, never a fixture preparation path.
        DB::table('paperless_payment_orders')->where('id', $application->paperlessPaymentOrders()->firstOrFail()->id)->update(['total_amount_cents' => 1]);
    } elseif ($change === 'line') {
        DB::table('paperless_payment_order_lines')->where('id', $application->paperlessPaymentOrders()->firstOrFail()->lines()->firstOrFail()->id)->update(['amount_cents' => 1]);
    } elseif ($change === 'routing') {
        $application->bploRoutingDetermination->works()->firstOrFail()->update(['office_code' => 'mpdo']);
    } elseif ($change === 'signature') {
        DB::table('signature_evidences')->where('signable_type', $application->declaration->getMorphClass())->where('signable_id', $application->declaration->id)->update(['evidence_digest' => str_repeat('0', 64)]);
    } elseif ($change === 'identity') {
        $application->update(['tracking_reference' => 'SUB-TAMPERED-TEST-ONLY']);
    } else {
        $metadata = $application->metadata;
        $metadata['treasury_browser_fixture']['production_policy_authority'] = true;
        $application->update(['metadata' => $metadata]);
    }
    $before = $application->fresh()->toJson();
    expect(fn () => $builder->prepare('T2026-NO-LOB'))->toThrow(RuntimeException::class, 'Fixture changed');
    expect($application->fresh()->toJson())->toBe($before);
})->with(['amount', 'line', 'routing', 'marker', 'signature', 'identity']);
