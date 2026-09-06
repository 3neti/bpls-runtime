<?php

namespace App\Actions;

use App\Models\PermitApplication;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CreateCitizenPermitApplicationDraftWithDocuments
{
    public function __construct(
        private readonly CaptureLifecycleCleanroomIntake $captureIntake,
        private readonly StoreCitizenPermitApplicationDocument $storeDocument,
    ) {}

    /**
     * @param  array<string, mixed>  $applicationData
     * @param  list<array{document_type: string, file: UploadedFile}>  $documents
     */
    public function handle(Request $request, array $applicationData, array $documents): PermitApplication
    {
        $storedDocuments = [];

        try {
            return $this->captureIntake->create(
                $request,
                $applicationData,
                function (PermitApplication $application) use ($documents, $request, &$storedDocuments): void {
                    foreach ($documents as $document) {
                        $storedDocuments[] = $this->storeDocument->handle($application, $document, $request->user());
                    }
                },
            )->load(['documents' => fn ($query) => $query->whereNull('removed_at')->with('media')]);
        } catch (Throwable $exception) {
            foreach ($storedDocuments as $document) {
                Storage::disk($document->storage_disk)->delete($document->path);
            }

            throw $exception;
        }
    }
}
