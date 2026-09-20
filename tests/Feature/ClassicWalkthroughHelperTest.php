<?php

use App\Actions\BuildClassicWalkthroughHelper;
use App\Models\LifecycleCleanroomRun;

function classicHelperInputs(): array
{
    return [new LifecycleCleanroomRun([
        'public_id' => 'TEST-CLASSIC',
        'status' => 'active',
        'actor_manifest' => [
            'ceremony' => LifecycleCleanroomRun::CeremonyClassicLifecycleV1,
            'semantic_classification' => 'synthetic_only',
            'production_liability' => false,
            'source_specimen' => ['id' => LifecycleCleanroomRun::SourceSpecimenCal2026001New2025],
            'actors' => ['citizen' => ['user_id' => 123]],
        ],
    ]), [
        'application_year' => 2025, 'type' => 'new', 'run_id' => 'TEST-CLASSIC',
        'staged_citizen_intake' => true,
        'source_specimen' => ['id' => LifecycleCleanroomRun::SourceSpecimenCal2026001New2025],
        'business_name' => 'Synthetic Fish Retailer', 'total_employee_count' => 1,
        'undertaking_accepted' => true, 'signature_facsimile' => 'never include',
        'application_documents' => ['never include'], 'lines' => ['never include'],
        'applicant_printed_name' => 'Never fill the declaration',
        'amount_centavos' => 100000, 'business_id' => 42,
    ]];
}

test('classic helper exposes applicant fields only and never fiscal or lodging authority', function () {
    [$run, $intake] = classicHelperInputs();
    expect(app(BuildClassicWalkthroughHelper::class)->handle($run, $intake, 123))
        ->toBe(['business_name' => 'Synthetic Fish Retailer', 'total_employee_count' => 1]);
});

test('classic helper fails closed outside each required binding', function (string $target, string $key, mixed $value) {
    [$run, $intake] = classicHelperInputs();
    if ($target === 'manifest') {
        $manifest = $run->actor_manifest;
        data_set($manifest, $key, $value);
        $run->actor_manifest = $manifest;
    } elseif ($target === 'intake') {
        data_set($intake, $key, $value);
    } else {
        $run->{$key} = $value;
    }
    expect(app(BuildClassicWalkthroughHelper::class)->handle($run, $intake, 123))->toBeNull();
})->with([
    ['run', 'status', 'closed'], ['run', 'new_application_id', 298],
    ['manifest', 'ceremony', LifecycleCleanroomRun::CeremonyNelsonReconciliationV1],
    ['manifest', 'semantic_classification', 'production'],
    ['manifest', 'production_liability', true],
    ['manifest', 'actors.citizen.user_id', 456],
    ['manifest', 'source_specimen.id', 'different'],
    ['intake', 'application_year', 2026], ['intake', 'type', 'renewal'],
    ['intake', 'run_id', 'different'], ['intake', 'staged_citizen_intake', false],
    ['intake', 'source_specimen.id', 'different'],
]);

test('ordinary intake has no walkthrough helper', function () {
    expect(app(BuildClassicWalkthroughHelper::class)->handle(null, null, 123))->toBeNull();
});

test('source addresses map to visible street controls without guessing address parts', function () {
    [$run, $intake] = classicHelperInputs();
    $intake['business_address'] = 'Complete synthetic business address';
    $intake['owner_address'] = 'Complete synthetic owner address';
    $fields = app(BuildClassicWalkthroughHelper::class)->handle($run, $intake, 123);
    expect($fields['business_street'])->toBe($intake['business_address'])
        ->and($fields['owner_street'])->toBe($intake['owner_address'])
        ->and($fields)->not->toHaveKeys(['business_address', 'owner_address', 'business_house_building_number']);
    $intake['business_street'] = 'Already structured street';
    expect(app(BuildClassicWalkthroughHelper::class)->handle($run, $intake, 123)['business_street'])
        ->toBe('Already structured street');
});
