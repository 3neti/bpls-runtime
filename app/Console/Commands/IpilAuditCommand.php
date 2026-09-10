<?php

namespace App\Console\Commands;

use App\Actions\VerifyIpilRescueCorpus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('ipil:audit {corpus : Local rescue corpus path} {--json : Emit JSON}')]
#[Description('Fail-closed entry point for future offline Ipil parity auditing.')]
final class IpilAuditCommand extends Command
{
    public function handle(VerifyIpilRescueCorpus $verify): int
    {
        try {
            $result = $verify->handle((string) $this->argument('corpus'));
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        $message = "Corpus {$result->corpusId} passed Rescue Corpus V1 verification, but ipil:audit is intentionally unavailable until deterministic historical projections exist.";

        if ($this->option('json')) {
            $this->line($this->json([
                'passed' => false,
                'corpus_integrity_passed' => true,
                'verified' => true,
                'audited' => false,
                'offline' => true,
                'domain_writes' => false,
                'corpus_id' => $result->corpusId,
                'error' => $message,
            ]));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }

    private function failure(Throwable $exception): int
    {
        if ($this->option('json')) {
            $this->line($this->json([
                'passed' => false,
                'corpus_integrity_passed' => false,
                'verified' => false,
                'audited' => false,
                'offline' => true,
                'domain_writes' => false,
                'error' => $exception->getMessage(),
            ]));
        } else {
            $this->error($exception->getMessage());
        }

        return self::FAILURE;
    }

    /** @param  array<string, mixed>  $payload */
    private function json(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
