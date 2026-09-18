<?php

use App\Actions\InspectInstallationReadiness;
use App\Actions\ProvisionLifecycleLaboratoryActors;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('the canonical database seed produces a workflow-ready installation', function () {
    config()->set('bpls_installation.seed_laboratory_actors', true);
    config()->set('bpls_installation.laboratory_actor_password', 'password');

    $this->seed(DatabaseSeeder::class);

    $readiness = app(InspectInstallationReadiness::class)->handle();
    $actorEmails = collect(app(ProvisionLifecycleLaboratoryActors::class)->definitions())->pluck('email');

    expect($readiness['pass'])->toBeTrue()
        ->and($readiness['failed'])->toBe([])
        ->and($readiness['checks'])->not->toContain(false)
        ->and($readiness['counts']['laboratory_actors'])->toBe(12)
        ->and(User::query()->whereIn('email', $actorEmails)->count())->toBe(12);
});

test('installation readiness fails closed when a laboratory actor retains a stale password', function () {
    config()->set('bpls_installation.seed_laboratory_actors', true);
    config()->set('bpls_installation.laboratory_actor_password', 'password');
    $this->seed(DatabaseSeeder::class);
    User::query()->where('email', 'intake@bpls-runtime.test')->sole()->update([
        'password' => Hash::make('old-laboratory-password'),
    ]);

    $beforeProvisioning = app(InspectInstallationReadiness::class)->handle(false);
    app(ProvisionLifecycleLaboratoryActors::class)->handle();
    $afterProvisioning = app(InspectInstallationReadiness::class)->handle();

    expect($beforeProvisioning['pass'])->toBeFalse()
        ->and($beforeProvisioning['failed'])->toContain('laboratory_actor_credentials')
        ->and($beforeProvisioning['checks']['laboratory_actors'])->toBeTrue()
        ->and($afterProvisioning['pass'])->toBeTrue()
        ->and($afterProvisioning['checks']['laboratory_actor_credentials'])->toBeTrue();
});
