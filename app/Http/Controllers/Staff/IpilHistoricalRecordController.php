<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildIpilHistoricalReadSurface;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Models\IpilHistoricalMediaEvidence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class IpilHistoricalRecordController extends Controller
{
    public function index(Request $request, BuildIpilHistoricalReadSurface $surface): Response
    {
        $this->authorizeHistory();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'], 'year' => ['nullable', 'integer', 'between:1900,2100'],
            'type' => ['nullable', 'string', 'max:80'], 'status' => ['nullable', 'string', 'max:80'],
            'barangay' => ['nullable', 'string', 'max:160'], 'classification' => ['nullable', 'string', 'max:160'],
            'sort' => ['nullable', Rule::in(['name', 'owner', 'barangay', 'applications'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        return Inertia::render('ipil-history/Index', [...$surface->index($filters), 'filters' => $filters]);
    }

    public function owner(int $owner, BuildIpilHistoricalReadSurface $surface): Response
    {
        $this->authorizeHistory();

        return Inertia::render('ipil-history/Owner', $surface->owner($owner));
    }

    public function business(int $business, BuildIpilHistoricalReadSurface $surface): Response
    {
        $this->authorizeHistory();

        return Inertia::render('ipil-history/Business', $surface->business($business));
    }

    public function application(int $application, BuildIpilHistoricalReadSurface $surface): Response
    {
        $this->authorizeHistory();

        return Inertia::render('ipil-history/Application', $surface->application($application));
    }

    public function document(int $business, int $document, BuildIpilHistoricalReadSurface $surface): StreamedResponse
    {
        $this->authorizeHistory();
        $evidence = $surface->document($business, $document);
        $media = $evidence->getFirstMedia(IpilHistoricalMediaEvidence::ApplicationDocumentsCollection);
        abort_unless($media instanceof Media, 404);

        return Storage::disk($media->disk)->response($media->getPathRelativeToRoot(), $evidence->original_filename ?? $media->file_name, ['Content-Type' => $media->mime_type], 'inline');
    }

    private function authorizeHistory(): void
    {
        Gate::authorize(UserPermission::ViewPermitApplications->value);
    }
}
