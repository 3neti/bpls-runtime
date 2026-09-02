<?php

use App\Actions\CancelPermitApplication;
use App\Actions\PermitApplicationStatusMutation;
use App\Enums\PermitApplicationStatus;
use App\Models\PermitApplication;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\File;

test('ordinary Eloquent paths cannot mutate a PermitApplication status', function (Closure $mutation): void {
    $application = PermitApplication::factory()->create();

    expect(fn () => $mutation($application))->toThrow(
        DomainException::class,
        'PermitApplication status may change only as a consequence of an authorized domain action.',
    );

    expect($application->fresh()->status)->toBe(PermitApplicationStatus::Draft);
})->with([
    'direct property assignment and save' => fn (PermitApplication $application) => (function () use ($application): void {
        $application->status = PermitApplicationStatus::Assessment;
        $application->save();
    })(),
    'model update' => fn (PermitApplication $application) => $application->update([
        'status' => PermitApplicationStatus::Assessment,
    ]),
    'fill then save' => fn (PermitApplication $application) => (function () use ($application): void {
        $application->fill(['status' => PermitApplicationStatus::Assessment]);
        $application->save();
    })(),
    'force fill used by an admin model writer' => fn (PermitApplication $application) => (function () use ($application): void {
        $application->forceFill(['status' => PermitApplicationStatus::Assessment]);
        $application->saveQuietly();
    })(),
    'model query update' => fn (PermitApplication $application) => PermitApplication::query()
        ->whereKey($application)
        ->update(['status' => PermitApplicationStatus::Assessment]),
]);

test('an arbitrary operational factory status requires the explicit fixture privilege', function () {
    expect(fn () => PermitApplication::factory()->create([
        'status' => PermitApplicationStatus::PendingPayment,
    ]))->toThrow(DomainException::class);

    $application = PermitApplication::factory()
        ->withStatus(PermitApplicationStatus::PendingPayment)
        ->create();

    expect($application->status)->toBe(PermitApplicationStatus::PendingPayment);

    expect(fn () => PermitApplication::factory()
        ->withStatus(PermitApplicationStatus::Assessment)
        ->create(['status' => PermitApplicationStatus::Released]))
        ->toThrow(LogicException::class, 'must match its explicit fixture privilege');
});

test('the canonical cancellation action remains authoritative for its status consequence', function () {
    $application = PermitApplication::factory()
        ->withStatus(PermitApplicationStatus::Assessment)
        ->create();
    $actor = User::factory()->create();

    $cancelled = app(CancelPermitApplication::class)->handle($application, $actor, 'Duplicate filing.');

    expect($cancelled->status)->toBe(PermitApplicationStatus::Cancelled)
        ->and($cancelled->metadata['status_history'])->toHaveCount(1)
        ->and($cancelled->metadata['terminal_state']['can_continue'])->toBeFalse();
});

test('fixture privilege is restored after an exception', function () {
    expect(fn () => PermitApplicationStatusMutation::forFactoryFixture(
        fn () => throw new RuntimeException('fixture failed'),
    ))->toThrow(RuntimeException::class, 'fixture failed');

    $application = PermitApplication::factory()->create();

    expect(fn () => $application->update([
        'status' => PermitApplicationStatus::Assessment,
    ]))->toThrow(DomainException::class);
});

test('only canonical actions and explicit historical or fixture infrastructure reference the privileged boundary', function () {
    $allowedFiles = [
        'Actions/CancelPermitApplication.php',
        'Actions/CreateAssessmentForPermitApplication.php',
        'Actions/CreatePaymentScheduleForAssessment.php',
        'Actions/ExecuteLegacyPermitApplications.php',
        'Actions/PermitApplicationStatusMutation.php',
        'Actions/PrepareBusinessPermitEvaluatorUatDataset.php',
        'Actions/RecordAssessmentDecision.php',
        'Actions/SubmitCitizenPermitApplication.php',
        'Evaluation/BusinessPermitEvaluationVersioner.php',
        'Models/Builders/PermitApplicationBuilder.php',
        'Models/PermitApplication.php',
    ];

    $references = collect(File::allFiles(app_path()))
        ->filter(fn (SplFileInfo $file): bool => str_contains($file->getContents(), 'PermitApplicationStatusMutation'))
        ->map(fn (SplFileInfo $file): string => str($file->getPathname())->after(app_path().DIRECTORY_SEPARATOR)->toString())
        ->sort()
        ->values()
        ->all();

    expect($references)->toBe($allowedFiles);
});
