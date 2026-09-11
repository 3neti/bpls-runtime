<?php

namespace App\Console\Commands;

use App\Actions\MaterializeIpilHistoricalCorpus;
use App\Actions\PersistIpilSeedPlan;
use App\Actions\PlanIpilRescueSeed;
use App\Actions\VerifyIpilRescueCorpus;
use App\Support\IpilRescue\Gate6ExecutionAuthorization;
use App\Support\IpilRescue\Gate8cExecutionAuthorization;
use App\Support\IpilRescue\IpilSeedMappingProfile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('ipil:seed {corpus : Local rescue corpus path} {--plan : Build the offline Gate 5 seed plan without domain writes} {--execute : Execute the explicitly authorized historical materialization} {--manifest= : Exact accepted private Execution Manifest path} {--authorization= : Private Gate 8C target-bound execution authorization} {--confirm-corpus= : Explicit canonical corpus confirmation} {--confirm-profile= : Explicit mapping profile confirmation} {--environment= : local-postgresql or private-historical-uat} {--json : Emit JSON}')]
#[Description('Plan offline or explicitly materialize the accepted Ipil corpus into a guarded local PostgreSQL database.')]
final class IpilSeedCommand extends Command
{
    public function handle(VerifyIpilRescueCorpus $verify, PlanIpilRescueSeed $planner, PersistIpilSeedPlan $persist, MaterializeIpilHistoricalCorpus $materialize): int
    {
        if ($this->option('execute')) {
            try {
                if ($this->option('plan')) {
                    throw new \RuntimeException('Choose either --plan or --execute; the two modes cannot be combined.');
                }
                if ($this->option('confirm-corpus') !== IpilSeedMappingProfile::CanonicalCorpusId
                    || $this->option('confirm-profile') !== IpilSeedMappingProfile::Name) {
                    throw new \RuntimeException('Gate 6 requires exact explicit corpus and mapping-profile confirmations.');
                }
                $verification = $verify->handle((string) $this->argument('corpus'));
                $plan = $planner->handle((string) $this->argument('corpus'));
                if (! hash_equals(Gate6ExecutionAuthorization::PlanFingerprint, $plan->fingerprint)) {
                    throw new \RuntimeException('The regenerated Seed Plan fingerprint differs from the accepted Gate 5 plan.');
                }
                $persist->handle($plan);
                $authorization = $this->option('environment') === Gate8cExecutionAuthorization::TargetEnvironment
                    ? Gate8cExecutionAuthorization::issue((string) $this->option('manifest'), (string) $this->option('authorization'))
                    : Gate6ExecutionAuthorization::issue((string) $this->option('manifest'), (string) $this->option('environment'));
                $result = $materialize->handle((string) $this->argument('corpus'), $authorization);
            } catch (Throwable $exception) {
                return $this->executionFailure($exception);
            }

            $payload = [
                'passed' => true,
                'verified' => true,
                'seeded' => true,
                'offline' => ! ($authorization instanceof Gate8cExecutionAuthorization),
                'domain_writes' => true,
                'source_write' => false,
                'network_access' => $authorization instanceof Gate8cExecutionAuthorization,
                'cloud_writes' => $authorization instanceof Gate8cExecutionAuthorization,
                'corpus_id' => $verification->corpusId,
                'corpus_fingerprint_sha256' => $verification->corpusFingerprint,
                'plan_fingerprint_sha256' => $plan->fingerprint,
                'manifest_fingerprint_sha256' => Gate6ExecutionAuthorization::ManifestFingerprint,
                'authorization_fingerprint_sha256' => $authorization->fingerprint,
            ] + $result->toArray();

            if ($this->option('json')) {
                $this->line($this->json($payload));
            } else {
                $this->info("Historical import run {$result->runId} completed against the explicitly authorized PostgreSQL target.");
            }

            return self::SUCCESS;
        }

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

        $message = "Corpus {$result->corpusId} passed Rescue Corpus V1 verification. Use the explicit, fully confirmed --execute interface only for the commissioned Gate 6 local PostgreSQL materialization.";

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

    private function executionFailure(Throwable $exception): int
    {
        if ($this->option('json')) {
            $this->line($this->json([
                'passed' => false,
                'seeded' => false,
                'source_write' => false,
                'network_access' => $this->option('environment') === Gate8cExecutionAuthorization::TargetEnvironment,
                'cloud_writes' => $this->option('environment') === Gate8cExecutionAuthorization::TargetEnvironment ? 'possibly-partial-review-private-run' : false,
                'error' => $this->option('environment') === Gate8cExecutionAuthorization::TargetEnvironment ? 'Gate 8C execution stopped; inspect private execution diagnostics.' : $exception->getMessage(),
            ]));
        } else {
            $this->error($exception->getMessage());
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
