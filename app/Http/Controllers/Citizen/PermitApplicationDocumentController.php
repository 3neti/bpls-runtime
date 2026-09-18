<?php

namespace App\Http\Controllers\Citizen;

use App\Actions\RemoveCitizenPermitApplicationDocument;
use App\Actions\StoreCitizenPermitApplicationDocument;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Citizen\StorePermitApplicationDocumentRequest;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
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
            ->with('status', 'Supporting document added to your draft.')
            ->with('lodging_ceremony_notice', 'Applicant documents changed. Review the final document set, then accept the Oath and sign again.');
    }

    public function download(Request $request, int $permitApplication, int $document): StreamedResponse
    {
        [$supportingDocument, $disk, $path] = $this->authorizedDocumentPath($request, $permitApplication, $document);

        return Storage::disk($disk)->download(
            $path,
            $supportingDocument->original_name,
            ['Content-Type' => $supportingDocument->mime_type],
        );
    }

    public function view(Request $request, int $permitApplication, int $document): StreamedResponse
    {
        [$supportingDocument, $disk, $path] = $this->authorizedDocumentPath($request, $permitApplication, $document);
        abort_unless(in_array($supportingDocument->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true), 415);

        return Storage::disk($disk)->response(
            $path,
            $supportingDocument->original_name,
            [
                'Content-Type' => $supportingDocument->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ],
            'inline',
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
            ->with('status', 'Applicant document removed from the draft.')
            ->with('lodging_ceremony_notice', 'Applicant documents changed. Review the final document set, then accept the Oath and sign again.');
    }

    private function ownedApplication(Request $request, int $permitApplication): PermitApplication
    {
        return PermitApplication::query()
            ->whereKey($permitApplication)
            ->visibleToPortalOwner($request->user())
            ->firstOrFail();
    }

    /** @return array{0: PermitApplicationDocument, 1: string, 2: string} */
    private function authorizedDocumentPath(Request $request, int $permitApplication, int $document): array
    {
        Gate::authorize(UserPermission::ViewOwnPermitApplicationDocuments->value);

        $application = $this->ownedApplication($request, $permitApplication);
        $supportingDocument = $application->documents()->findOrFail($document);
        $supportingDocument->loadMissing('media');

        return $supportingDocument->media === null
            ? [$supportingDocument, $supportingDocument->storage_disk, $supportingDocument->path]
            : [$supportingDocument, $supportingDocument->media->disk, $supportingDocument->media->getPathRelativeToRoot()];
    }
}
