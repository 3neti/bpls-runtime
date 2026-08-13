<?php

namespace App\Actions;

use App\Models\Storyboard;
use App\Models\StoryboardFrame;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class RenderStoryboardVideo
{
    public function handle(Storyboard $storyboard, string $outputPath): void
    {
        $storyboard->loadMissing('frames');

        if ($storyboard->frames->isEmpty()) {
            throw new RuntimeException('Storyboard video export requires at least one frame.');
        }

        $workingDirectory = Storage::disk('local')->path('storyboard-video/'.$storyboard->id.'-'.str()->random(12));
        $publicOutputPath = Storage::disk('public')->path($outputPath);

        if (! is_dir($workingDirectory) && ! mkdir($workingDirectory, 0755, true) && ! is_dir($workingDirectory)) {
            throw new RuntimeException('Unable to create storyboard video workspace.');
        }

        if (! is_dir(dirname($publicOutputPath)) && ! mkdir(dirname($publicOutputPath), 0755, true) && ! is_dir(dirname($publicOutputPath))) {
            throw new RuntimeException('Unable to create storyboard video output directory.');
        }

        $concatFile = $workingDirectory.'/frames.txt';
        $concatLines = [];

        foreach ($storyboard->frames as $frame) {
            $framePath = $workingDirectory.'/frame-'.$frame->position.'.png';
            $this->renderFrameImage($frame, $framePath);
            $concatLines[] = "file '".$this->escapeConcatPath($framePath)."'";
            $concatLines[] = 'duration '.$frame->duration_seconds;
        }

        $lastFrame = $storyboard->frames->last();
        if ($lastFrame instanceof StoryboardFrame) {
            $concatLines[] = "file '".$this->escapeConcatPath($workingDirectory.'/frame-'.$lastFrame->position.'.png')."'";
        }

        file_put_contents($concatFile, implode("\n", $concatLines)."\n");

        $result = Process::timeout(300)->run([
            'ffmpeg',
            '-y',
            '-f',
            'concat',
            '-safe',
            '0',
            '-i',
            $concatFile,
            '-vf',
            'format=yuv420p',
            '-movflags',
            '+faststart',
            $publicOutputPath,
        ]);

        $this->deleteDirectory($workingDirectory);

        if ($result->failed()) {
            throw new RuntimeException('FFmpeg storyboard export failed: '.$result->errorOutput());
        }
    }

    private function renderFrameImage(StoryboardFrame $frame, string $path): void
    {
        $image = imagecreatetruecolor(1280, 720);

        if ($image === false) {
            throw new RuntimeException('Unable to create storyboard frame image.');
        }

        $background = imagecolorallocate($image, 28, 35, 43);
        $panel = imagecolorallocate($image, 245, 247, 250);
        $text = imagecolorallocate($image, 24, 24, 27);
        $muted = imagecolorallocate($image, 82, 82, 91);
        $accent = imagecolorallocate($image, 25, 94, 46);

        imagefilledrectangle($image, 0, 0, 1280, 720, $background);
        imagefilledrectangle($image, 64, 64, 1216, 656, $panel);
        imagefilledrectangle($image, 64, 64, 1216, 76, $accent);

        imagestring($image, 5, 96, 112, 'Frame '.$frame->position.' - '.$frame->duration_seconds.' sec', $muted);
        imagestring($image, 5, 96, 154, $this->limit($frame->title, 86), $text);

        $this->imagestringWrapped($image, 'Action: '.($frame->description ?? 'No action notes recorded.'), 96, 230, 150, $text);
        $this->imagestringWrapped($image, 'Dialogue: '.($frame->dialogue ?? 'No dialogue recorded.'), 96, 410, 150, $muted);

        if ($frame->image_path !== null) {
            imagestring($image, 3, 96, 590, 'Visual reference: '.$this->limit($frame->image_path, 96), $muted);
        } else {
            imagestring($image, 3, 96, 590, 'Visual placeholder', $muted);
        }

        imagepng($image, $path);
        imagedestroy($image);
    }

    private function imagestringWrapped(\GdImage $image, string $text, int $x, int $y, int $characters, int $color): void
    {
        foreach (str($text)->wordWrap($characters, "\n", true)->explode("\n") as $line) {
            imagestring($image, 4, $x, $y, $line, $color);
            $y += 24;
        }
    }

    private function limit(string $text, int $characters): string
    {
        return str($text)->limit($characters, '...')->toString();
    }

    private function escapeConcatPath(string $path): string
    {
        return str_replace("'", "'\\''", $path);
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/*') ?: [] as $path) {
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
