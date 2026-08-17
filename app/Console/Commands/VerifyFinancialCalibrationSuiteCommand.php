<?php

namespace App\Console\Commands;

use App\Actions\VerifyFinancialCalibrationSuite;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('financial:verify-calibrations {--run-id= : Stable suite run reference} {--json : Write only structured output}')]
#[Description('Verify private Golden Financial Specimens without evaluating formulas or activating policy.')]
class VerifyFinancialCalibrationSuiteCommand extends Command
{
    private const SpecimenRoot = 'financial-calibrations/specimens';

    private const RunRoot = 'financial-calibrations/runs';

    public function handle(VerifyFinancialCalibrationSuite $action): int
    {
        try {
            $runReference = $this->runReference();
            $manifests = $this->manifests();
            $report = $action->handle($manifests, $runReference);
            $root = $this->writeEvidence($runReference, $report);
        } catch (Throwable $exception) {
            return $this->failCommand($exception->getMessage());
        }

        $result = [
            'passed' => $report['summary']['historical_suite_passed'],
            'run_id' => $runReference,
            'specimens' => $report['summary']['specimen_count'],
            'historical_reproduction_passed' => $report['summary']['historical_reproduction_passed'],
            'historical_reproduction_failed' => $report['summary']['historical_reproduction_failed'],
            'future_policy_status' => $report['summary']['future_policy_suite_status'],
            'future_policy_pending' => $report['summary']['future_policy_pending'],
            'formulas_evaluated' => false,
            'financial_policy_activated' => false,
            'financial_domain_writes' => false,
            'artifacts' => Storage::disk('local')->path($root),
        ];

        $this->outputResult($result);

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<array<string, mixed>> */
    private function manifests(): array
    {
        $disk = Storage::disk('local');
        $manifests = [];
        foreach ($disk->directories(self::SpecimenRoot) as $directory) {
            $path = $directory.'/manifest.json';
            if (! $disk->exists($path)) {
                continue;
            }
            $manifest = json_decode($disk->get($path), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($manifest)) {
                throw new RuntimeException("Financial calibration manifest [{$path}] is not a JSON object.");
            }
            $manifests[] = $manifest;
        }

        return $manifests;
    }

    /** @param array<string, mixed> $report */
    private function writeEvidence(string $runReference, array $report): string
    {
        $root = self::RunRoot.'/'.$runReference;
        $this->writeImmutable($root.'/summary.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        $this->writeImmutable($root.'/review.md', "# Financial Calibration Suite Review\n\nReviewer status: Pending\nReviewer:\nMunicipal authority / role:\nReviewed at:\nDecision reference:\nNotes:\n");

        return $root;
    }

    private function writeImmutable(string $path, string $contents): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            if (! hash_equals(hash('sha256', $contents), hash('sha256', $disk->get($path)))) {
                throw new RuntimeException('Stable financial calibration run is already bound to different evidence.');
            }
            if (! $disk->setVisibility($path, 'private')) {
                throw new RuntimeException('Financial calibration suite evidence could not be made private.');
            }

            return;
        }
        if (! $disk->put($path, $contents)) {
            throw new RuntimeException('Financial calibration suite evidence could not be written.');
        }
        if (! $disk->setVisibility($path, 'private')) {
            throw new RuntimeException('Financial calibration suite evidence could not be made private.');
        }
    }

    private function runReference(): string
    {
        $runReference = $this->option('run-id');
        if (! is_string($runReference) || trim($runReference) === '') {
            throw new RuntimeException('A stable --run-id is required.');
        }

        return trim($runReference);
    }

    /** @param array<string, mixed> $result */
    private function outputResult(array $result): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return;
        }

        $this->line('Financial calibration suite: '.$result['run_id']);
        $this->line("Historical reproduction: {$result['historical_reproduction_passed']} passed / {$result['historical_reproduction_failed']} failed");
        $this->line("Future policy: {$result['future_policy_status']} / {$result['future_policy_pending']} pending");
        $this->line('Formula evaluation, policy activation, and financial writes: none');
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
