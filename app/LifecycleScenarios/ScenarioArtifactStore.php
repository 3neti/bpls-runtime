<?php

namespace App\LifecycleScenarios;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

final class ScenarioArtifactStore
{
    public function __construct(
        public readonly string $scenarioKey,
        public readonly string $runId,
    ) {}

    public function rootRelativePath(): string
    {
        return 'lifecycle-scenarios/'.$this->scenarioKey.'/'.$this->runId;
    }

    public function absolutePath(): string
    {
        return $this->disk()->path($this->rootRelativePath());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function putJson(string $path, array $data): void
    {
        $this->put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    public function appendJsonLine(string $path, mixed $data): void
    {
        $fullPath = $this->rootRelativePath().'/'.$path;
        $this->disk()->append($fullPath, json_encode($data, JSON_UNESCAPED_SLASHES));
    }

    public function put(string $path, string $contents): void
    {
        $this->disk()->put($this->rootRelativePath().'/'.$path, $contents);
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($this->rootRelativePath().'/'.$path);
    }

    public function delete(string $path): void
    {
        $this->disk()->delete($this->rootRelativePath().'/'.$path);
    }

    public function readJson(string $path): ?array
    {
        if (! $this->exists($path)) {
            return null;
        }

        $decoded = json_decode($this->disk()->get($this->rootRelativePath().'/'.$path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk('local');
    }
}
