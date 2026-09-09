<?php

use App\Actions\InspectInstallationReadiness;
use App\Actions\ProvisionLifecycleLaboratoryActors;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

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
