<?php

namespace App\Http\Controllers\Staff;

use App\Actions\StorePermitApplicationDocument;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StorePermitApplicationDocumentRequest;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PermitApplicationDocumentController extends Controller
{
    public function store(StorePermitApplicationDocumentRequest $request, PermitApplication $permitApplication, StorePermitApplicationDocument $storeDocument): RedirectResponse
    {
        $storeDocument->handle($permitApplication, $request->validated(), $request->user());

        return to_route('staff.permit-applications.show', $permitApplication)
            ->with('status', 'Supporting document recorded.');
    }

    public function download(PermitApplication $permitApplication, PermitApplicationDocument $document): StreamedResponse
    {
        Gate::authorize(UserPermission::ViewPermitApplications->value);
        abort_unless($document->permit_application_id === $permitApplication->id, 404);

        return Storage::disk($document->storage_disk)->download(
            $document->path,
            $document->original_name,
            ['Content-Type' => $document->mime_type],
        );
    }
}
