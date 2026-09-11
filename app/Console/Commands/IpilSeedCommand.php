<?php

namespace App\Console\Commands;

use App\Actions\PersistIpilSeedPlan;
use App\Actions\PlanIpilRescueSeed;
use App\Actions\VerifyIpilRescueCorpus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('ipil:seed {corpus : Local rescue corpus path} {--plan : Build the offline Gate 5 seed plan without domain writes} {--json : Emit JSON}')]
#[Description('Plan a verified Ipil rescue corpus offline; real materialization remains disabled.')]
final class IpilSeedCommand extends Command
{
    public function handle(VerifyIpilRescueCorpus $verify, PlanIpilRescueSeed $planner, PersistIpilSeedPlan $persist): int
    {
        if ($this->option('plan')) {
            try {
                $plan = $planner->handle((string) $this->argument('corpus'));
                $artifacts = $persist->handle($plan);
            } catch (Throwable $exception) {
                return $this->failure($exception);
            }

            $payload = $plan->toArray() + [
                'passed' => true,
                'planned' => true,
                'seeded' => false,
                'offline' => true,
                'domain_writes' => false,
                'artifacts' => $artifacts,
            ];

            if ($this->option('json')) {
                $this->line($this->json($payload));
            } else {
                $this->info("Offline seed plan {$payload['plan_id']} is ready. No domain records were written.");
                $this->line("Plan: {$artifacts['plan']}");
                $this->line("Execution manifest: {$artifacts['execution_manifest']}");
            }

            return self::SUCCESS;
        }

        try {
            $result = $verify->handle((string) $this->argument('corpus'));
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        $message = "Corpus {$result->corpusId} passed Rescue Corpus V1 verification. Real Ipil seed execution remains disabled until Gate 6 authorization.";

        if ($this->option('json')) {
            $this->line($this->json([
                'passed' => false,
                'corpus_integrity_passed' => true,
                'verified' => true,
                'seeded' => false,
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
                'seeded' => false,
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
