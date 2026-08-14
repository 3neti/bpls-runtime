<?php

namespace App\Actions;

use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StorePermitApplicationDocument
{
    /**
     * @param  array{label: string, file: UploadedFile, remarks?: string|null}  $data
     */
    public function handle(PermitApplication $permitApplication, array $data, User $uploadedBy): PermitApplicationDocument
    {
        $file = $data['file'];
        $extension = $file->extension();
        $path = $file->storeAs(
            "permit-applications/{$permitApplication->id}/documents",
            Str::uuid().($extension === '' ? '' : ".{$extension}"),
            'local',
        );

        if ($path === false) {
            throw new RuntimeException('Unable to store the permit application document.');
        }

        try {
            return $permitApplication->documents()->create([
                'uploaded_by_id' => $uploadedBy->id,
                'label' => $data['label'],
                'original_name' => $file->getClientOriginalName(),
                'storage_disk' => 'local',
                'path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => (int) $file->getSize(),
                'remarks' => $data['remarks'] ?? null,
                'source_snapshot' => [
                    'classification' => 'supporting_evidence',
                    'requirement_catalog_status' => 'unresolved',
                    'policy_note' => 'Document receipt does not establish statutory sufficiency, approval, or permit eligibility.',
                ],
                'uploaded_at' => now(),
            ])->load('uploadedBy');
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }
}
