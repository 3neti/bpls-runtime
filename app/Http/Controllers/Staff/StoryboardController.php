<?php

namespace App\Http\Controllers\Staff;

use App\Actions\RenderStoryboardPdf;
use App\Actions\SaveStoryboard;
use App\Enums\StoryboardExportFormat;
use App\Enums\StoryboardExportStatus;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStoryboardRequest;
use App\Http\Requests\Staff\UpdateStoryboardRequest;
use App\Jobs\GenerateStoryboardVideo;
use App\Models\Storyboard;
use App\Models\StoryboardExport;
use App\Models\StoryboardFrame;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StoryboardController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(UserPermission::ManageStoryboards->value);

        $storyboards = Storyboard::query()
            ->withCount('frames')
            ->latest()
            ->paginate(15)
            ->through(fn (Storyboard $storyboard): array => [
                'id' => $storyboard->id,
                'title' => $storyboard->title,
                'summary' => $storyboard->summary,
                'frames_count' => $storyboard->frames_count,
                'updated_at' => $storyboard->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('storyboards/Index', [
            'storyboards' => $storyboards,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize(UserPermission::ManageStoryboards->value);

        return Inertia::render('storyboards/Edit', [
            'storyboard' => null,
            'durationLimits' => [
                'min' => 1,
                'max' => 30,
            ],
        ]);
    }

    public function store(StoreStoryboardRequest $request, SaveStoryboard $saveStoryboard): RedirectResponse
    {
        $storyboard = $saveStoryboard->create($request->validated(), $request->user());

        return to_route('staff.storyboards.edit', $storyboard);
    }

    public function show(Storyboard $storyboard): RedirectResponse
    {
        Gate::authorize(UserPermission::ManageStoryboards->value);

        return to_route('staff.storyboards.edit', $storyboard);
    }

    public function edit(Storyboard $storyboard): Response
    {
        Gate::authorize(UserPermission::ManageStoryboards->value);

        $storyboard->load(['frames', 'exports']);

        return Inertia::render('storyboards/Edit', [
            'storyboard' => $this->storyboardPayload($storyboard),
            'durationLimits' => [
                'min' => 1,
                'max' => 30,
            ],
        ]);
    }

    public function update(UpdateStoryboardRequest $request, Storyboard $storyboard, SaveStoryboard $saveStoryboard): RedirectResponse
    {
        $saveStoryboard->update($storyboard, $request->validated());

        return to_route('staff.storyboards.edit', $storyboard);
    }

    public function destroy(Storyboard $storyboard): RedirectResponse
    {
        Gate::authorize(UserPermission::ManageStoryboards->value);

        $storyboard->delete();

        return to_route('staff.storyboards.index');
    }

    public function exportPdf(Storyboard $storyboard, RenderStoryboardPdf $renderStoryboardPdf): RedirectResponse
    {
        Gate::authorize(UserPermission::ManageStoryboards->value);

        $export = $storyboard->exports()->create([
            'format' => StoryboardExportFormat::Pdf,
            'status' => StoryboardExportStatus::Processing,
        ]);
        $path = 'storyboards/'.$storyboard->id.'/exports/storyboard-'.$storyboard->id.'-'.$export->id.'.pdf';

        Storage::disk('public')->put($path, $renderStoryboardPdf->handle($storyboard));

        $export->update([
            'status' => StoryboardExportStatus::Completed,
            'path' => $path,
            'completed_at' => now(),
        ]);

        return to_route('staff.storyboards.edit', $storyboard);
    }

    public function exportVideo(Storyboard $storyboard): RedirectResponse
    {
        Gate::authorize(UserPermission::ManageStoryboards->value);

        $export = $storyboard->exports()->create([
            'format' => StoryboardExportFormat::Video,
            'status' => StoryboardExportStatus::Pending,
        ]);

        GenerateStoryboardVideo::dispatch($export->id);

        return to_route('staff.storyboards.edit', $storyboard);
    }

    /**
     * @return array<string, mixed>
     */
    private function storyboardPayload(Storyboard $storyboard): array
    {
        return [
            'id' => $storyboard->id,
            'title' => $storyboard->title,
            'summary' => $storyboard->summary,
            'frames' => $storyboard->frames
                ->map(fn (StoryboardFrame $frame): array => [
                    'id' => $frame->id,
                    'position' => $frame->position,
                    'title' => $frame->title,
                    'image_path' => $frame->image_path,
                    'image_url' => $frame->imageUrl(),
                    'description' => $frame->description,
                    'dialogue' => $frame->dialogue,
                    'duration_seconds' => $frame->duration_seconds,
                ])
                ->values(),
            'exports' => $storyboard->exports
                ->map(fn (StoryboardExport $export): array => [
                    'id' => $export->id,
                    'format' => $export->format->value,
                    'status' => $export->status->value,
                    'download_url' => $export->downloadUrl(),
                    'failure_message' => $export->failure_message,
                    'completed_at' => $export->completed_at?->toIso8601String(),
                    'created_at' => $export->created_at?->toIso8601String(),
                ])
                ->values(),
        ];
    }
}
