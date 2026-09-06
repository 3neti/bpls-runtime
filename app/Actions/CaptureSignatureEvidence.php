<?php

namespace App\Actions;

use App\Models\SignatureEvidence;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CaptureSignatureEvidence
{
    public function handle(Model $signable, User $signer, string $purpose, UploadedFile $facsimile): SignatureEvidence
    {
        $checksum = hash_file('sha256', $facsimile->getRealPath());
        if (! is_string($checksum)) {
            throw new RuntimeException('Unable to checksum the captured signature facsimile.');
        }

        $capturedAt = now();
        $digest = hash('sha256', implode('|', [
            $signable->getMorphClass(),
            $signable->getKey(),
            $signer->id,
            $purpose,
            $capturedAt->toIso8601String(),
            $checksum,
        ]));

        $evidence = null;
        try {
            return DB::transaction(function () use ($signable, $signer, $purpose, $facsimile, $checksum, $capturedAt, $digest, &$evidence): SignatureEvidence {
                $evidence = SignatureEvidence::query()->create([
                    'signer_id' => $signer->id,
                    'signable_type' => $signable->getMorphClass(),
                    'signable_id' => $signable->getKey(),
                    'purpose' => $purpose,
                    'method' => 'captured_facsimile',
                    'evidence_digest' => $digest,
                    'captured_at' => $capturedAt,
                    'source_snapshot' => [
                        'signable_type' => $signable->getMorphClass(),
                        'signable_id' => $signable->getKey(),
                        'signer_id' => $signer->id,
                        'purpose' => $purpose,
                        'captured_at' => $capturedAt->toIso8601String(),
                        'method' => 'captured_facsimile',
                        'facsimile_checksum_sha256' => $checksum,
                        'legal_semantics' => 'visual_facsimile_evidence_only',
                        'statutory_digital_signature_claimed' => false,
                    ],
                ]);

                $media = $evidence
                    ->addMedia($facsimile)
                    ->usingName('Signature facsimile')
                    ->withCustomProperties(['checksum_sha256' => $checksum, 'immutable_evidence_digest' => $digest])
                    ->toMediaCollection(SignatureEvidence::FacsimileCollection, 'local');

                if ($media->size < 1) {
                    throw new RuntimeException('The captured signature facsimile is empty.');
                }

                return $evidence->load(['signer', 'media']);
            });
        } catch (Throwable $exception) {
            $evidence?->getFirstMedia(SignatureEvidence::FacsimileCollection)?->delete();
            throw $exception;
        }
    }
}
