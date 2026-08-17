<?php

namespace App\Console\Commands;

use App\Actions\ProposeLegacyClearanceTypeReconciliations;
use App\Models\LegacyImportBatch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('legacy:propose-clearance-reconciliations {batch : Exact legacy import batch ID} {--run-id= : Stable proposal reference} {--json : Write only structured output}')]
#[Description('Propose exact clearance-type crosswalk candidates without recording municipal acceptance or migration mappings.')]
class ProposeLegacyClearanceTypeReconciliationsCommand extends Command
{
    public function handle(ProposeLegacyClearanceTypeReconciliations $action): int
    {
        try {
            $runReference = $this->runReference();
            $batch = LegacyImportBatch::query()->with('source')->findOrFail($this->batchId());
            $report = $action->handle($batch, $runReference);
            $root = $this->writeEvidence($batch, $runReference, $report);
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $result = [
            'passed' => true,
            'run_id' => $runReference,
            'batch_id' => $batch->id,
            'affected_records' => $report['result']['affected_record_count'],
            'missing_source_identifiers' => $report['result']['missing_source_identifier_count'],
            'exact_candidates' => $report['result']['exact_candidate_count'],
            'accepted' => 0,
            'domain_writes' => false,
            'migration_executed' => false,
            'artifacts' => Storage::disk('local')->path($root),
        ];

        $this->outputResult($result);

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $report */
    private function writeEvidence(LegacyImportBatch $batch, string $runReference, array $report): string
    {
        $root = "legacy-migrations/{$batch->source->key}/{$batch->run_reference}/reconciliation/clearance-types/{$runReference}";
        $this->writeImmutable($root.'/proposal.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        $this->writeImmutable($root.'/review.md', "# Clearance-Type Reconciliation Review\n\nReviewer status: Pending\nReviewer:\nAuthority / role:\nReviewed at:\nDecision reference:\nNotes:\n");
        $this->writeImmutable($root.'/municipal-acceptance.md', $this->municipalAcceptancePacket($report));

        return $root;
    }

    private function writeImmutable(string $path, string $contents): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            if (! hash_equals(hash('sha256', $contents), hash('sha256', $disk->get($path)))) {
                throw new RuntimeException('Stable clearance reconciliation run is already bound to different evidence.');
            }
            if (! $disk->setVisibility($path, 'private')) {
                throw new RuntimeException('Clearance reconciliation evidence could not be made private.');
            }

            return;
        }
        if (! $disk->put($path, $contents)) {
            throw new RuntimeException('Clearance reconciliation evidence could not be written.');
        }
        if (! $disk->setVisibility($path, 'private')) {
            throw new RuntimeException('Clearance reconciliation evidence could not be made private.');
        }
    }

    /** @param array<string, mixed> $report */
    private function municipalAcceptancePacket(array $report): string
    {
        $lines = [
            '# Municipal Clearance-Type Reconciliation Decision',
            '',
            'Status: Pending municipal decision',
            '',
            'This packet presents exact source-backed candidates. It does not accept a mapping or authorize migration.',
            '',
            'Snapshot SHA-256: `'.$report['source']['archive_sha256'].'`',
            '',
        ];

        foreach ($report['proposals'] as $index => $proposal) {
            $source = $proposal['source_evidence_variants'][0] ?? [];
            $candidate = $proposal['candidate_evidence'] ?? [];
            $lines = [...$lines,
                '## Candidate '.($index + 1),
                '',
                '- Affected historical records: '.$proposal['affected_records'],
                '- Historical source identifier: `'.$this->markdown($proposal['source_legacy_id']).'`',
                '- Proposed target identifier: `'.$this->markdown($proposal['candidate_target_legacy_id'] ?? '').'`',
                '- Evidence basis: `'.$this->markdown($proposal['basis']).'`',
                '- Historical evidence: '.$this->evidenceLabel($source),
                '- Proposed current evidence: '.$this->evidenceLabel($candidate),
                '',
                'Decision:',
                '',
                '- [ ] Accept exact historical-to-current crosswalk',
                '- [ ] Reject proposed crosswalk',
                '- [ ] Preserve as quarantined historical evidence',
                '',
                'Decision authority / role:',
                '',
                'Decision date:',
                '',
                'Decision reference and notes:',
                '',
            ];
        }

        $lines = [...$lines,
            '## Execution Boundary',
            '',
            'Signed decisions must be recorded through the versioned reconciliation contract before any mapping or migration proposal can become executable.',
            '',
            'This form itself creates no reconciliation row, domain write, migration, or cutover authority.',
            '',
        ];

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $evidence */
    private function evidenceLabel(array $evidence): string
    {
        return implode(' / ', array_map(
            fn (string $field): string => $this->markdown($evidence[$field] ?? ''),
            ['name', 'short_name', 'certificate_name'],
        ));
    }

    private function markdown(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        return str_replace(['|', "\r", "\n"], ['\\|', ' ', ' '], $value);
    }

    private function runReference(): string
    {
        $runReference = $this->option('run-id');
        if (! is_string($runReference) || trim($runReference) === '') {
            throw new RuntimeException('A stable --run-id is required.');
        }

        return trim($runReference);
    }

    private function batchId(): int
    {
        $batchId = filter_var($this->argument('batch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($batchId)) {
            throw new RuntimeException('The batch argument must be an exact positive legacy import batch ID.');
        }

        return $batchId;
    }

    /** @param array<string, mixed> $result */
    private function outputResult(array $result): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return;
        }

        $this->line('Clearance reconciliation proposal: '.$result['run_id']);
        $this->line("Affected: {$result['affected_records']} records / {$result['missing_source_identifiers']} missing identifiers");
        $this->line("Exact candidates: {$result['exact_candidates']} / accepted: 0");
        $this->line('Domain writes and migration: none');
        $this->line('Artifacts: '.$result['artifacts']);
    }

    private function failCommand(string $message): int
    {
        if ($this->option('json')) {
            $this->line(json_encode(['passed' => false, 'error' => $message], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }
}
