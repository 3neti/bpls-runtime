<?php

namespace App\Http\Controllers\Citizen;

use App\Actions\RemoveCitizenPermitApplicationDocument;
use App\Actions\StoreCitizenPermitApplicationDocument;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Citizen\StorePermitApplicationDocumentRequest;
use App\Models\PermitApplication;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PermitApplicationDocumentController extends Controller
{
    public function store(
        StorePermitApplicationDocumentRequest $request,
        int $permitApplication,
        StoreCitizenPermitApplicationDocument $storeDocument,
    ): RedirectResponse {
        $application = $this->ownedApplication($request, $permitApplication);
        $file = $request->file('file');
        $documentType = $request->validated('document_type');

        if (! $file instanceof UploadedFile || ! is_string($documentType)) {
            return back()->withErrors(['document' => 'Choose a configured document type and file.']);
        }

        try {
            $storeDocument->handle($application, [
                'document_type' => $documentType,
                'file' => $file,
            ], $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['document' => $exception->getMessage()]);
        }

        return to_route(
            $request->validated('return_to') === 'edit'
                ? 'citizen.permit-applications.edit'
                : 'citizen.permit-applications.show',
            $application,
        )
            ->with('status', 'Supporting document added to your draft.');
    }

    public function download(Request $request, int $permitApplication, int $document): StreamedResponse
    {
        Gate::authorize(UserPermission::ViewOwnPermitApplicationDocuments->value);

        $application = $this->ownedApplication($request, $permitApplication);
        $supportingDocument = $application->documents()->findOrFail($document);

        if ($supportingDocument->media !== null) {
            return Storage::disk($supportingDocument->media->disk)->download(
                $supportingDocument->media->getPathRelativeToRoot(),
                $supportingDocument->original_name,
                ['Content-Type' => $supportingDocument->mime_type],
            );
        }

        return Storage::disk($supportingDocument->storage_disk)->download(
            $supportingDocument->path,
            $supportingDocument->original_name,
            ['Content-Type' => $supportingDocument->mime_type],
        );
    }

    public function destroy(Request $request, int $permitApplication, int $document, RemoveCitizenPermitApplicationDocument $removeDocument): RedirectResponse
    {
        $application = $this->ownedApplication($request, $permitApplication);
        $supportingDocument = $application->documents()->findOrFail($document);

        try {
            $removeDocument->handle($supportingDocument, $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['document' => $exception->getMessage()]);
        }

        return to_route(
            $request->string('return_to')->value() === 'edit'
                ? 'citizen.permit-applications.edit'
                : 'citizen.permit-applications.show',
            $application,
        )
            ->with('status', 'Applicant document removed from the draft.');
    }

    private function ownedApplication(Request $request, int $permitApplication): PermitApplication
    {
        return PermitApplication::query()
            ->whereKey($permitApplication)
            ->visibleToPortalOwner($request->user())
            ->firstOrFail();
    }
}
