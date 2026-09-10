<?php

namespace App\Console\Commands;

use App\Actions\CullIpilRescueCorpus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('ipil:cull
    {--preflight : Validate source authentication and every safety invariant without creating a snapshot}
    {--resume= : Resume one exact private IN_PROGRESS or FAILED snapshot}
    {--accept-source-access : Confirm this is an explicit network read of the authorized Ipil source}
    {--confirm-source-access : Second confirmation required before creating or resuming a snapshot}
    {--json : Write only a redacted structured summary}')]
#[Description('Explicitly transport authorized read-only Ipil evidence into a private immutable Rescue Corpus V1 snapshot.')]
final class IpilCullCommand extends Command
{
    public function handle(CullIpilRescueCorpus $cull): int
    {
        if (! $this->option('accept-source-access')) {
            return $this->failCommand('The explicit --accept-source-access confirmation is required.');
        }

        try {
            if ($this->option('preflight')) {
                $result = $cull->preflight();
            } else {
                if (! $this->option('confirm-source-access')) {
                    return $this->failCommand('Both source-access confirmations are required to create or resume a snapshot.');
                }

                $resume = $this->option('resume');
                $result = $cull->handle(is_string($resume) && $resume !== '' ? $resume : null)->toArray();
            }
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->renderSummary($result);
        }

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $result */
    private function renderSummary(array $result): void
    {
        if ($this->option('preflight')) {
            $this->info('Ipil cull preflight: PASS');
            $this->line('Source access: explicit network / read only');
            $this->line('Destination: local private / Git-ignored');
            $this->line('Corpus schema: '.$result['corpus_schema']);

            return;
        }

        $this->info('Ipil Rescue Corpus finalized: '.$result['snapshot']);
        $this->line('Database: '.$result['database']['rows'].' rows across '.$result['database']['tables'].' tables');
        $this->line('Media: '.$result['media']['objects_retrieved'].' objects / '.$result['media']['bytes_rescued'].' bytes');
        $this->line('Media gaps: '.$result['media']['missing_source_bytes'].' missing / '.$result['media']['retrieval_failures'].' failed / '.$result['media']['unresolved_objects'].' unresolved');
        $this->line('Pricing evidence: '.$result['pricing']['records'].' records');
        $this->line('Exceptions: '.$result['exceptions']);
        $this->line('Verification: '.$result['verification']);
        $this->line('Corpus fingerprint: '.$result['corpus_fingerprint']);
        $this->line('Snapshot: '.$result['snapshot_path']);
    }

    private function failCommand(string $message): int
    {
        if ($this->option('json')) {
            $this->line(json_encode([
                'passed' => false,
                'error' => $message,
                'source_write' => false,
                'domain_writes' => false,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }
}
