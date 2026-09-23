<?php

namespace App\Console\Commands;

use App\Assessment\PricingCatalogDraftPlanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('pricing:plan-definitions {--catalog= : Source YAML path} {--source-sha256= : Required reviewed SHA-256}')]
#[Description('Validate non-executable pricing drafts without importing or changing prices.')]
class PlanPricingDefinitions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PricingCatalogDraftPlanner $planner): int
    {
        try {
            $drafts = $planner->plan(
                $this->option('catalog') ?: database_path('seeders/data/ipil_municipal_fee_catalog.v1.yaml'),
                $this->option('source-sha256') ?? '',
            );
            $issues = [];
            $branches = 0;
            $definitions = 0;
            foreach ($drafts as $draft) {
                foreach ($draft->sourceEvidence['issues'] as $issue) {
                    $issues[$issue] = ($issues[$issue] ?? 0) + 1;
                }
                $tax = $draft->sourceEvidence['tax_definition'];
                if ($tax !== null) {
                    $definitions++;
                    $branches += count($tax['branches']);
                }
            }
            ksort($issues);
            $this->line(json_encode([
                'source_sha256' => $drafts[0]->sourceSha256,
                'draft_count' => count($drafts),
                'tax_definition_count' => $definitions,
                'tax_branch_count' => $branches,
                'issues' => $issues,
                'database_writes' => false,
                'executable' => false,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
