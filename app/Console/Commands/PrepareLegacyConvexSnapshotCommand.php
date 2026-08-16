<?php

namespace App\Console\Commands;

use App\Actions\PrepareLegacyConvexSnapshot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:prepare-convex-snapshot
    {archive : Path to an operator-supplied Convex snapshot ZIP}
    {--run-id= : Stable operator-provided intake reference}
    {--deployment= : Convex deployment name without URL or credentials}
    {--captured-at= : ISO-8601 snapshot capture timestamp including timezone}
    {--accept-production-data : Confirm private handling of production row data}
    {--confirm-production-data : Second explicit production-data confirmation}
    {--json : Write only structured output}')]
#[Description('Fingerprint and prepare an authorized Convex snapshot for checksum-verified legacy staging.')]
class PrepareLegacyConvexSnapshotCommand extends Command
{
    public function handle(PrepareLegacyConvexSnapshot $prepareSnapshot): int
    {
        try {
            if (! $this->option('accept-production-data') || ! $this->option('confirm-production-data')) {
                throw new RuntimeException('Both --accept-production-data and --confirm-production-data are required.');
            }

            $runReference = $this->requiredStringOption('run-id');
            $deployment = $this->requiredStringOption('deployment');
            $capturedAt = $this->requiredStringOption('captured-at');
            $result = $prepareSnapshot->handle(
                (string) $this->argument('archive'),
                $runReference,
                $deployment,
                $capturedAt,
            );
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $summary = [
            'passed' => true,
            'run_id' => $runReference,
            'source' => $result['source_key'],
            'archive_sha256' => $result['archive_checksum'],
            'tables' => $result['table_count'],
            'records' => $result['record_count'],
            'storage_files' => $result['storage_file_count'],
            'storage_bytes' => $result['storage_bytes'],
            'production_data_present' => true,
            'staged' => false,
            'domain_writes' => false,
            'cutover_authorized' => false,
            'manifest' => $result['manifest_path'],
            'artifacts' => Storage::disk('local')->path($result['artifact_root']),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Convex snapshot intake: '.$runReference);
            $this->line('Source: '.$result['source_key']);
            $this->line("Records: {$result['record_count']} across {$result['table_count']} tables");
            $this->line("File storage: {$result['storage_file_count']} files / {$result['storage_bytes']} bytes");
            $this->line('Staged: no');
            $this->line('Domain writes: none');
            $this->line('Manifest: '.$result['manifest_path']);
            $this->line('Artifacts: '.$summary['artifacts']);
        }

        return self::SUCCESS;
    }

    private function requiredStringOption(string $key): string
    {
        $value = $this->option($key);
        if (! is_string($value) || $value === '') {
            throw new RuntimeException("A stable --{$key} is required.");
        }

        return $value;
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
