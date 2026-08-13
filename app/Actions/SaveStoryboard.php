<?php

namespace App\Actions;

use App\Models\Storyboard;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class SaveStoryboard
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): Storyboard
    {
        return DB::transaction(function () use ($data, $user): Storyboard {
            $storyboard = Storyboard::create([
                'created_by_id' => $user->id,
                'title' => $data['title'],
                'summary' => $data['summary'] ?? null,
            ]);

            $this->replaceFrames($storyboard, $data['frames']);

            return $storyboard->load(['frames', 'exports']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Storyboard $storyboard, array $data): Storyboard
    {
        return DB::transaction(function () use ($storyboard, $data): Storyboard {
            $storyboard->update([
                'title' => $data['title'],
                'summary' => $data['summary'] ?? null,
            ]);

            $this->replaceFrames($storyboard, $data['frames']);

            return $storyboard->load(['frames', 'exports']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $frames
     */
    private function replaceFrames(Storyboard $storyboard, array $frames): void
    {
        $previousImagePaths = $storyboard->frames()->pluck('image_path')->filter();
        $storyboard->frames()->delete();

        foreach (array_values($frames) as $index => $frame) {
            $imagePath = Arr::get($frame, 'existing_image_path');

            if (! $previousImagePaths->contains($imagePath)) {
                $imagePath = null;
            }

            if (isset($frame['image']) && $frame['image'] instanceof UploadedFile) {
                $imagePath = $frame['image']->store('storyboards/'.$storyboard->id.'/frames', 'public');
            }

            $storyboard->frames()->create([
                'position' => $index + 1,
                'title' => $frame['title'],
                'image_path' => $imagePath,
                'description' => $frame['description'] ?? null,
                'dialogue' => $frame['dialogue'] ?? null,
                'duration_seconds' => $frame['duration_seconds'],
            ]);
        }

        $previousImagePaths
            ->filter()
            ->diff(collect($frames)->pluck('existing_image_path')->filter())
            ->each(fn (string $path) => rescue(fn () => Storage::disk('public')->delete($path), report: false));
    }
}
