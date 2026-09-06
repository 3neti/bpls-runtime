<?php

namespace App\Actions;

use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

class StorePermitApplicationDocument
{
    /**
     * @param  array{label: string, file: UploadedFile, remarks?: string|null, source?: string}  $data
     */
    public function handle(PermitApplication $permitApplication, array $data, User $uploadedBy): PermitApplicationDocument
    {
        if ($permitApplication->isHistoricalEvidenceOnly()) {
            throw new RuntimeException("Historical evidence application [{$permitApplication->id}] cannot receive operational document uploads.");
        }

        $file = $data['file'];
        $checksum = hash_file('sha256', $file->getRealPath());
        if (! is_string($checksum)) {
            throw new RuntimeException('Unable to checksum the permit application document.');
        }

        $media = $permitApplication
            ->addMedia($file)
            ->preservingOriginal()
            ->usingName($data['label'])
            ->withCustomProperties([
                'document_type' => $data['document_type'] ?? 'other',
                'checksum_sha256' => $checksum,
                'uploaded_by_id' => $uploadedBy->id,
                'semantic_classification' => 'applicant_supplied_evidence',
            ])
            ->toMediaCollection(PermitApplication::ApplicationDocumentsCollection, 'local');

        try {
            return $permitApplication->documents()->create([
                'uploaded_by_id' => $uploadedBy->id,
                'media_id' => $media->id,
                'label' => $data['label'],
                'document_type' => $data['document_type'] ?? 'other',
                'version' => $this->nextVersion($permitApplication, (string) ($data['document_type'] ?? 'other')),
                'original_name' => $media->file_name,
                'storage_disk' => 'local',
                'path' => $media->getPathRelativeToRoot(),
                'mime_type' => $media->mime_type,
                'size_bytes' => $media->size,
                'checksum_sha256' => $checksum,
                'remarks' => $data['remarks'] ?? null,
                'source_snapshot' => [
                    'classification' => 'applicant_supplied_evidence',
                    'media_id' => $media->id,
                    'media_collection' => PermitApplication::ApplicationDocumentsCollection,
                    'requirement_catalog_status' => 'unresolved',
                    'submitted_via' => $data['source'] ?? 'staff_intake',
                    'policy_note' => 'Document receipt does not establish statutory sufficiency, approval, or permit eligibility.',
                ],
                'uploaded_at' => now(),
            ])->load('uploadedBy');
        } catch (Throwable $exception) {
            $media->delete();

            throw $exception;
        }
    }

    private function nextVersion(PermitApplication $application, string $documentType): int
    {
        return ((int) $application->documents()->where('document_type', $documentType)->max('version')) + 1;
    }
}
