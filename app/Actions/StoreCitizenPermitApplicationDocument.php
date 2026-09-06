<?php

namespace App\Actions;

use App\Enums\PermitApplicationStatus;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use App\Models\User;
use App\Support\ApplicationDocumentTypeCatalog;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StoreCitizenPermitApplicationDocument
{
    public function __construct(
        private readonly StorePermitApplicationDocument $storePermitApplicationDocument,
        private readonly ApplicationDocumentTypeCatalog $documentTypeCatalog,
    ) {}

    /**
     * @param  array{document_type: string, file: UploadedFile}  $data
     */
    public function handle(PermitApplication $permitApplication, array $data, User $uploadedBy): PermitApplicationDocument
    {
        $storedDocument = null;

        try {
            return DB::transaction(function () use ($permitApplication, $data, $uploadedBy, &$storedDocument): PermitApplicationDocument {
                $draft = PermitApplication::query()
                    ->lockForUpdate()
                    ->findOrFail($permitApplication->id);

                if ($draft->submitted_by_id !== $uploadedBy->id) {
                    throw new DomainException('This permit application draft does not belong to the authenticated citizen.');
                }

                if (
                    $draft->status !== PermitApplicationStatus::Draft
                    || $draft->application_number !== null
                    || $draft->assessments()->exists()
                    || ! $draft->canContinue()
                ) {
                    throw new DomainException('Supporting documents may only be added while this permit application remains a citizen draft.');
                }

                $documentType = $this->documentTypeCatalog->resolve($data['document_type']);
                $replacedDocumentIds = $documentType['allows_multiple']
                    ? []
                    : $draft->documents()
                        ->where('document_type', $documentType['code'])
                        ->whereNull('removed_at')
                        ->lockForUpdate()
                        ->pluck('id')
                        ->all();

                $storedDocument = $this->storePermitApplicationDocument->handle($draft, [
                    ...$data,
                    'label' => $documentType['label'],
                    'document_type' => $documentType['code'],
                    'source' => 'citizen_portal',
                    'document_type_catalog_revision' => $this->documentTypeCatalog->revision(),
                ], $uploadedBy);

                if ($replacedDocumentIds !== []) {
                    $draft->documents()
                        ->whereKey($replacedDocumentIds)
                        ->update(['removed_at' => now()]);
                }

                return $storedDocument;
            });
        } catch (Throwable $exception) {
            if ($storedDocument instanceof PermitApplicationDocument) {
                Storage::disk($storedDocument->storage_disk)->delete($storedDocument->path);
            }

            throw $exception;
        }
    }
}
