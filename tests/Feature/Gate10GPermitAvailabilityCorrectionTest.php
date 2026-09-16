<?php

use App\Actions\DescribePermitArtifact;
use App\Models\PermitApplication;
use App\Models\ProvisionalUatPermitCompletion;
use Illuminate\Support\Carbon;

test('permit document availability follows issued evidence, not application or verification identity', function () {
    $application = PermitApplication::factory()->create([
        'tracking_reference' => 'PVA-1-393fc096aee20e87',
    ]);

    $beforeIssue = app(DescribePermitArtifact::class)->handle($application);

    expect($beforeIssue['available'])->toBeFalse()
        ->and($beforeIssue['status'])->toBe('not_issued')
        ->and($beforeIssue['verification_reference'])->toStartWith('PVA-')
        ->and($beforeIssue['permit_pdf_url'])->toBeNull();

    ProvisionalUatPermitCompletion::factory()->create([
        'permit_application_id' => $application->id,
        'issued_at' => Carbon::now(),
        'permit_number' => 'SYN-0001',
    ]);

    $afterIssue = app(DescribePermitArtifact::class)->handle($application->fresh());

    expect($afterIssue['available'])->toBeTrue()
        ->and($afterIssue['status'])->toBe('issued_document_available')
        ->and($afterIssue['permit_pdf_url'])->not->toBeNull();
});

test('ordinary permit presentation does not advertise an unavailable document', function () {
    $artifact = file_get_contents(resource_path('js/pages/citizen/permit-applications/Show.vue'));
    $readiness = file_get_contents(app_path('Actions/DescribePermitReleaseReadiness.php'));

    expect($artifact)->toContain('permitApplication.permit_artifact &&')
        ->and($artifact)->toContain('permitApplication.permit_artifact.available')
        ->and($readiness)->toContain("'permit_artifact_available' => \$permitDocumentIssued");
});
