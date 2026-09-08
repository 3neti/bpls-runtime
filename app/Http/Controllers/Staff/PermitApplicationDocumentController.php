<?php

namespace App\Http\Controllers\Staff;

use App\Actions\StorePermitApplicationDocument;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StorePermitApplicationDocumentRequest;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PermitApplicationDocumentController extends Controller
{
    public function store(StorePermitApplicationDocumentRequest $request, PermitApplication $permitApplication, StorePermitApplicationDocument $storeDocument): RedirectResponse
    {
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        $documentData = [
            'label' => $request->string('label')->toString(),
            'file' => $file,
        ];
        $documentType = $request->validated('document_type');
        if (is_string($documentType) && $documentType !== '') {
            $documentData['document_type'] = $documentType;
        }
        $remarks = $request->validated('remarks');
        if (is_string($remarks) && $remarks !== '') {
            $documentData['remarks'] = $remarks;
        }

        $storeDocument->handle($permitApplication, $documentData, $request->user());

        return to_route('staff.permit-applications.show', $permitApplication)
            ->with('status', 'Supporting document recorded.');
    }

    public function download(PermitApplication $permitApplication, PermitApplicationDocument $document): StreamedResponse
    {
        [$disk, $path] = $this->authorizedDocumentPath($permitApplication, $document);

        return Storage::disk($disk)->download(
            $path,
            $document->original_name,
            ['Content-Type' => $document->mime_type],
        );
    }

    public function view(PermitApplication $permitApplication, PermitApplicationDocument $document): StreamedResponse
    {
        [$disk, $path] = $this->authorizedDocumentPath($permitApplication, $document);

        return Storage::disk($disk)->response(
            $path,
            $document->original_name,
            ['Content-Type' => $document->mime_type],
            'inline',
        );
    }

    /** @return array{0: string, 1: string} */
    private function authorizedDocumentPath(PermitApplication $permitApplication, PermitApplicationDocument $document): array
    {
        Gate::authorize(UserPermission::ViewPermitApplications->value);
        abort_unless($document->permit_application_id === $permitApplication->id, 404);

        $document->loadMissing('media');
        $media = $document->getRelation('media');

        return [
            $media instanceof Media ? $media->disk : $document->storage_disk,
            $media instanceof Media ? $media->getPathRelativeToRoot() : $document->path,
        ];
    }
}
