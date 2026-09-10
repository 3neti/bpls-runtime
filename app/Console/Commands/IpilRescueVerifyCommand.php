<?php

namespace App\Console\Commands;

use App\Actions\VerifyIpilRescueCorpus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('ipil:rescue:verify {corpus : Local rescue corpus path} {--json : Emit JSON}')]
#[Description('Verify Rescue Corpus V1 integrity and semantic contracts offline.')]
final class IpilRescueVerifyCommand extends Command
{
    public function handle(VerifyIpilRescueCorpus $verify): int
    {
        try {
            $result = $verify->handle((string) $this->argument('corpus'));
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        if ($this->option('json')) {
            $this->line($this->json($result->toArray()));

            return self::SUCCESS;
        }

        $this->info("Ipil rescue corpus {$result->corpusId} passed integrity and semantic verification.");
        $this->line("Fingerprint: {$result->corpusFingerprint}");
        $this->line("Files verified: {$result->verifiedFileCount}");
        $this->line("Source identities: {$result->sourceIdentityCount}");
        $this->line('Network access: none');
        $this->line('Evidence repair: none');
        $this->line('Domain writes: none');

        return self::SUCCESS;
    }

    private function failure(Throwable $exception): int
    {
        if ($this->option('json')) {
            $this->line($this->json([
                'passed' => false,
                'integrity_passed' => false,
                'verification_complete' => false,
                'offline' => true,
                'healed' => false,
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
