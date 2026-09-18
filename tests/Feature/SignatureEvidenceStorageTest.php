<?php

use App\Actions\CaptureSignatureEvidence;
use App\Models\PermitApplicationDeclaration;
use App\Models\SignatureEvidence;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('signature capture stores checksum-bound bytes on the configured private disk', function (string $disk) {
    Storage::fake('local');
    Storage::fake('s3');
    config(['filesystems.signature_evidence_disk' => $disk]);
    $file = UploadedFile::fake()->image('signature.png');
    $checksum = hash_file('sha256', $file->getRealPath());

    $evidence = app(CaptureSignatureEvidence::class)->handle(
        PermitApplicationDeclaration::factory()->create(),
        User::factory()->create(),
        'applicant_lodging',
        $file,
    );
    $media = $evidence->getFirstMedia(SignatureEvidence::FacsimileCollection);

    expect($media->disk)->toBe($disk)
        ->and(hash('sha256', Storage::disk($disk)->get($media->getPathRelativeToRoot())))->toBe($checksum)
        ->and($evidence->source_snapshot['facsimile_checksum_sha256'])->toBe($checksum);

    $digest = $evidence->evidence_digest;
    config(['filesystems.signature_evidence_disk' => $disk === 'local' ? 's3' : 'local']);
    $persisted = $evidence->fresh();
    $persistedMedia = $persisted->getFirstMedia(SignatureEvidence::FacsimileCollection);
    expect($persistedMedia->disk)->toBe($disk)
        ->and($persisted->evidence_digest)->toBe($digest)
        ->and(hash('sha256', Storage::disk($persistedMedia->disk)->get($persistedMedia->getPathRelativeToRoot())))->toBe($checksum);
})->with(['local', 's3']);
