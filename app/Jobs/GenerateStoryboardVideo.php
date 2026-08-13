<?php

namespace App\Jobs;

use App\Actions\RenderStoryboardVideo;
use App\Enums\StoryboardExportStatus;
use App\Models\StoryboardExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateStoryboardVideo implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 30];

    public function __construct(public int $storyboardExportId) {}

    public function handle(RenderStoryboardVideo $renderStoryboardVideo): void
    {
        $export = StoryboardExport::query()
            ->with('storyboard.frames')
            ->findOrFail($this->storyboardExportId);

        $export->update([
            'status' => StoryboardExportStatus::Processing,
            'failure_message' => null,
        ]);

        $path = 'storyboards/'.$export->storyboard_id.'/exports/storyboard-'.$export->storyboard_id.'-'.$export->id.'.mp4';

        $renderStoryboardVideo->handle($export->storyboard, $path);

        $export->update([
            'status' => StoryboardExportStatus::Completed,
            'path' => $path,
            'completed_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        StoryboardExport::query()
            ->whereKey($this->storyboardExportId)
            ->update([
                'status' => StoryboardExportStatus::Failed,
                'failure_message' => $exception?->getMessage() ?? 'Storyboard video export failed.',
            ]);
    }
}
