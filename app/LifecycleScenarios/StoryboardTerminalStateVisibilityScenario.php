<?php

namespace App\LifecycleScenarios;

use App\Actions\RenderStoryboardPdf;
use App\Actions\SaveStoryboard;
use App\Enums\StoryboardExportFormat;
use App\Enums\StoryboardExportStatus;
use App\Jobs\GenerateStoryboardVideo;
use App\Models\Storyboard;
use App\Models\StoryboardExport;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class StoryboardTerminalStateVisibilityScenario
{
    public function __construct(
        private readonly SaveStoryboard $saveStoryboard,
        private readonly RenderStoryboardPdf $renderStoryboardPdf,
        private readonly ScenarioManifest $scenarioManifest,
        private readonly ScenarioSummaryRenderer $summaryRenderer,
    ) {}

    /**
     * @param  array<string, User>  $actors
     * @return array<string, mixed>
     */
    public function prepare(LifecycleScenarioDefinition $scenario, string $runId, array $actors, ScenarioArtifactStore $artifactStore): array
    {
        $existingManifest = $artifactStore->readJson('manifest.json');
        if (is_array($existingManifest) && ($existingManifest['result']['terminal'] ?? null) === 'passed') {
            return $existingManifest;
        }

        $operator = $actors['operator'] ?? throw new RuntimeException('Scenario operator actor was not resolved.');
        $manifest = $this->scenarioManifest->initial($scenario, $runId, $actors);
        $storyboard = $this->saveStoryboard->create([
            'title' => 'Lifecycle scenario '.$runId,
            'summary' => 'Disposable storyboard created by the lifecycle scenario runner.',
            'frames' => [
                [
                    'title' => 'Intake confirmation',
                    'description' => 'Operator confirms that the storyboard workspace can persist ordered frame state.',
                    'dialogue' => 'The first lifecycle frame is visible in the staff UI.',
                    'duration_seconds' => 4,
                ],
                [
                    'title' => 'Terminal export evidence',
                    'description' => 'Operator requests exports and verifies the generated artifact state.',
                    'dialogue' => 'The exported PDF and queued video are visible to the reviewer.',
                    'duration_seconds' => 5,
                ],
            ],
        ], $operator);

        $storyboard->update([
            'summary' => $storyboard->summary."\nRun reference: {$runId}",
        ]);

        $pdfExport = $this->createPdfExport($storyboard);
        $videoExport = $this->createVideoExport($storyboard);

        $steps = [
            $this->step('actors-resolved', 'Resolve actual application users', ['operator_id' => $operator->id], ['operator_id' => $operator->id]),
            $this->step('storyboard-created', 'Create storyboard through SaveStoryboard action', ['frames' => 2], ['frames' => $storyboard->frames()->count(), 'storyboard_id' => $storyboard->id]),
            $this->step('pdf-export-completed', 'Generate PDF export through application renderer and storage', ['status' => 'completed'], ['status' => $pdfExport->status->value, 'export_id' => $pdfExport->id]),
            $this->step('video-export-queued', 'Queue video export job without running external FFmpeg in prepare phase', ['status' => 'pending'], ['status' => $videoExport->status->value, 'export_id' => $videoExport->id]),
        ];

        foreach ($steps as $step) {
            $artifactStore->appendJsonLine('terminal/action-log.jsonl', $step);
        }

        $manifest['resources'] = [
            'record_type' => 'storyboard',
            'record_id' => $storyboard->id,
            'public_reference' => 'STORYBOARD-'.$storyboard->id,
            'list_url' => route('staff.storyboards.index', absolute: false),
            'detail_url' => route('staff.storyboards.edit', $storyboard, false),
            'pdf_export_id' => $pdfExport->id,
            'video_export_id' => $videoExport->id,
        ];
        $manifest['steps'] = $steps;
        $manifest['result']['terminal'] = collect($steps)->every(fn (array $step): bool => $step['passed']) ? 'passed' : 'failed';
        $manifest['result']['passed'] = $manifest['result']['terminal'] === 'passed';
        $manifest['artifacts'] = [
            'root' => '.',
            'pdf_export' => $pdfExport->path,
        ];

        $artifactStore->putJson('terminal/prepare.json', [
            'storyboard_id' => $storyboard->id,
            'pdf_export_id' => $pdfExport->id,
            'video_export_id' => $videoExport->id,
            'run_id' => $runId,
        ]);
        $artifactStore->putJson('terminal/execution.json', [
            'steps' => $steps,
            'external_calls' => 0,
            'irreversible_actions' => false,
            'notifications' => false,
        ]);
        $artifactStore->putJson('manifest.json', $manifest);
        $artifactStore->put('review.md', $this->summaryRenderer->reviewMarkdown());
        $this->writeStoryboardArtifacts($artifactStore, $manifest, $storyboard);

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    public function audit(array $manifest, ScenarioArtifactStore $artifactStore): array
    {
        $storyboard = Storyboard::query()
            ->with(['frames', 'exports'])
            ->findOrFail($manifest['resources']['record_id']);
        $browserReport = $artifactStore->readJson('browser/report.json') ?? [
            'result' => [
                'passed' => false,
            ],
            'checks' => [],
        ];

        $pdfExports = $storyboard->exports
            ->where('format', StoryboardExportFormat::Pdf)
            ->where('status', StoryboardExportStatus::Completed)
            ->count();
        $videoExports = $storyboard->exports
            ->where('format', StoryboardExportFormat::Video)
            ->where('status', StoryboardExportStatus::Pending)
            ->count();

        $checks = [
            $this->step('audit-canonical-frame-count', 'Canonical frame count matches terminal expectation', ['frames' => 2], ['frames' => $storyboard->frames->count()]),
            $this->step('audit-pdf-export-count', 'Completed PDF export exists exactly once', ['pdf_exports' => 1], ['pdf_exports' => $pdfExports]),
            $this->step('audit-video-export-count', 'Pending video export exists exactly once', ['video_exports' => 1], ['video_exports' => $videoExports]),
            $this->step('audit-browser-result', 'Browser evidence runner passed', ['browser' => true], ['browser' => (bool) data_get($browserReport, 'result.passed')]),
        ];

        $passed = collect($checks)->every(fn (array $check): bool => $check['passed']);

        $manifest['steps'] = [
            ...($manifest['steps'] ?? []),
            ...$checks,
        ];
        $manifest['result']['audit'] = $passed ? 'passed' : 'failed';
        $manifest['result']['browser'] = data_get($browserReport, 'result.passed') ? 'passed' : 'failed';
        $manifest['result']['passed'] = $manifest['result']['terminal'] === 'passed'
            && $manifest['result']['browser'] === 'passed'
            && $manifest['result']['audit'] === 'passed';
        $manifest['artifacts']['screenshots'] = data_get($browserReport, 'artifacts.screenshots', []);

        $artifactStore->putJson('terminal/audit.json', [
            'checks' => $checks,
            'passed' => $passed,
            'canonical' => [
                'storyboard_id' => $storyboard->id,
                'title' => $storyboard->title,
                'frames' => $storyboard->frames->count(),
                'pdf_exports' => $pdfExports,
                'video_exports' => $videoExports,
            ],
            'browser' => $browserReport,
        ]);
        $artifactStore->putJson('manifest.json', $manifest);
        $artifactStore->put('summary.html', $this->summaryRenderer->html($manifest));

        return $manifest;
    }

    private function createPdfExport(Storyboard $storyboard): StoryboardExport
    {
        $export = $storyboard->exports()->create([
            'format' => StoryboardExportFormat::Pdf,
            'status' => StoryboardExportStatus::Processing,
        ]);
        $path = 'storyboards/'.$storyboard->id.'/exports/storyboard-'.$storyboard->id.'-'.$export->id.'.pdf';

        Storage::disk('public')->put($path, $this->renderStoryboardPdf->handle($storyboard));

        $export->update([
            'status' => StoryboardExportStatus::Completed,
            'path' => $path,
            'completed_at' => now(),
        ]);

        return $export->refresh();
    }

    private function createVideoExport(Storyboard $storyboard): StoryboardExport
    {
        $export = $storyboard->exports()->create([
            'format' => StoryboardExportFormat::Video,
            'status' => StoryboardExportStatus::Pending,
        ]);

        GenerateStoryboardVideo::dispatch($export->id);

        return $export->refresh();
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     * @return array<string, mixed>
     */
    private function step(string $key, string $action, array $expected, array $actual): array
    {
        return [
            'key' => $key,
            'actor' => 'operator',
            'action' => $action,
            'expected' => $expected,
            'actual' => $actual,
            'passed' => $expected == $actual || collect($expected)->every(fn (mixed $value, string $field): bool => ($actual[$field] ?? null) === $value),
            'occurred_at' => now()->toIso8601String(),
            'evidence' => $actual,
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeStoryboardArtifacts(ScenarioArtifactStore $artifactStore, array $manifest, Storyboard $storyboard): void
    {
        $artifactStore->putJson('storyboard/storyboard.json', [
            'title' => $storyboard->title,
            'summary' => $storyboard->summary,
            'frames' => $storyboard->frames()
                ->get(['position', 'title', 'description', 'dialogue', 'duration_seconds'])
                ->toArray(),
        ]);
        $artifactStore->put('storyboard/storyboard.html', $this->summaryRenderer->html($manifest));
        $artifactStore->put('storyboard/storyboard.pdf', $this->renderStoryboardPdf->handle($storyboard));
    }
}
