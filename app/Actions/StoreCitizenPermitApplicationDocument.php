<?php

namespace App\Actions;

use App\Enums\PermitApplicationStatus;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StoreCitizenPermitApplicationDocument
{
    public function __construct(
        private readonly StorePermitApplicationDocument $storePermitApplicationDocument,
    ) {}

    /**
     * @param  array{label: string, file: UploadedFile, remarks?: string|null}  $data
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

                $storedDocument = $this->storePermitApplicationDocument->handle($draft, [
                    ...$data,
                    'source' => 'citizen_portal',
                ], $uploadedBy);

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
