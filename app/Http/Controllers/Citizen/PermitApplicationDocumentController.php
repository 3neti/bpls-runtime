<?php

namespace App\Http\Controllers\Citizen;

use App\Actions\StoreCitizenPermitApplicationDocument;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Citizen\StorePermitApplicationDocumentRequest;
use App\Models\PermitApplication;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        try {
            $storeDocument->handle($application, $request->validated(), $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['document' => $exception->getMessage()]);
        }

        return to_route('citizen.permit-applications.show', $application)
            ->with('status', 'Supporting document added to your draft.');
    }

    public function download(Request $request, int $permitApplication, int $document): StreamedResponse
    {
        Gate::authorize(UserPermission::ViewOwnPermitApplicationDocuments->value);

        $application = $this->ownedApplication($request, $permitApplication);
        $supportingDocument = $application->documents()->findOrFail($document);

        return Storage::disk($supportingDocument->storage_disk)->download(
            $supportingDocument->path,
            $supportingDocument->original_name,
            ['Content-Type' => $supportingDocument->mime_type],
        );
    }

    private function ownedApplication(Request $request, int $permitApplication): PermitApplication
    {
        return PermitApplication::query()
            ->whereKey($permitApplication)
            ->whereBelongsTo($request->user(), 'submittedBy')
            ->firstOrFail();
    }
}
