<?php

namespace App\Console\Commands;

use App\Actions\StageLegacyDocumentObjects;
use App\Models\LegacyDocumentObjectStagingRun;
use App\Models\LegacyImportBatch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:stage-document-objects
    {batch : Exact staged legacy import batch ID}
    {manifest : Absolute or working-directory-relative object manifest path}
    {--run-id= : Stable operator-provided staging reference}
    {--stage : Confirm private checksum-verified object staging and scope reconciliation}
    {--confirm-stage : Second explicit confirmation}
    {--json : Write only structured output}')]
#[Description('Stage checksum-verified legacy business-document objects with explicit permit-application scope in local or testing environments.')]
class StageLegacyDocumentObjectsCommand extends Command
{
    public function handle(StageLegacyDocumentObjects $action): int
    {
        try {
            if (! $this->option('stage') || ! $this->option('confirm-stage')) {
                throw new RuntimeException('Both --stage and --confirm-stage are required for document object staging.');
            }
            $runReference = $this->option('run-id');
            if (! is_string($runReference) || $runReference === '') {
                throw new RuntimeException('A stable --run-id is required.');
            }
            $batchId = filter_var($this->argument('batch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (! is_int($batchId)) {
                throw new RuntimeException('The batch argument must be an exact positive legacy import batch ID.');
            }

            $run = $action->handle(
                LegacyImportBatch::query()->with('source')->findOrFail($batchId),
                (string) $this->argument('manifest'),
                $runReference,
            );
            $artifactPath = $this->writeEvidence($run);
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $result = [
            'passed' => true,
            'run_id' => $run->run_reference,
            'staging_run_id' => $run->id,
            'status' => $run->status->value,
            'objects_declared' => $run->object_count,
            'objects_staged' => $run->staged_count,
            'domain_writes' => 0,
            'artifacts' => Storage::disk('local')->path($artifactPath),
        ];
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Legacy document staging: '.$run->run_reference);
            $this->line('Status: '.$run->status->value);
            $this->line("Objects: {$run->staged_count}/{$run->object_count}");
            $this->line('Domain records written: none');
            $this->line('Artifacts: '.Storage::disk('local')->path($artifactPath));
        }

        return self::SUCCESS;
    }

    private function writeEvidence(LegacyDocumentObjectStagingRun $run): string
    {
        $run->loadMissing(['importBatch.source', 'reconciliations']);
        $root = "legacy-migrations/{$run->importBatch->source->key}/{$run->importBatch->run_reference}/document-object-staging/{$run->run_reference}";
        $report = [
            'schema_version' => 'bpls.legacy-document-object-staging-report.v1',
            'run_id' => $run->run_reference,
            'staging_run_id' => $run->id,
            'manifest_checksum' => $run->manifest_checksum,
            'status' => $run->status->value,
            'counts' => ['declared' => $run->object_count, 'staged' => $run->staged_count],
            'objects' => $run->reconciliations->map(fn ($item): array => [
                'reconciliation_id' => $item->id,
                'legacy_record_id' => $item->legacy_record_id,
                'application_mapping_id' => $item->legacy_application_id_mapping_id,
                'item_key' => $item->item_key,
                'object_checksum' => $item->object_checksum,
                'size_bytes' => $item->size_bytes,
                'mime_type' => $item->mime_type,
                'status' => $item->status->value,
                'scope_decision_recorded' => true,
                'documentary_sufficiency_asserted' => false,
            ])->all(),
            'safety' => [
                'environment' => app()->environment(),
                'private_staging' => true,
                'checksum_size_and_mime_verified' => true,
                'application_scope_reconciled' => true,
                'domain_writes' => false,
                'legacy_document_status_authority_migrated' => false,
                'documentary_sufficiency_asserted' => false,
                'raw_storage_references_in_report' => false,
                'original_filenames_in_report' => false,
                'source_paths_in_report' => false,
            ],
            'completed_at' => $run->completed_at?->toIso8601String(),
        ];

        if (! Storage::disk('local')->put($root.'/staging.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n")
            || ! Storage::disk('local')->put($root.'/review.md', "# Legacy Document Object Staging Review\n\nReviewer status: Pending\nReviewer:\nReviewed at:\nNotes:\n")) {
            throw new RuntimeException('Legacy document staging evidence could not be written to private storage.');
        }

        return $root;
    }

    private function failCommand(string $message): int
    {
        if ($this->option('json')) {
            $this->line(json_encode(['passed' => false, 'error' => $message], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }
}
