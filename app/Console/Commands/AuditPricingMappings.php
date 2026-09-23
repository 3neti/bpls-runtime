<?php

namespace App\Console\Commands;

use App\Assessment\PricingMappingCoverageAudit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('pricing:audit-mappings {--source-sha256= : Exact source cohort SHA-256}')]
#[Description('Report stored mapping coverage without accepting policy or changing records.')]
class AuditPricingMappings extends Command
{
    public function handle(PricingMappingCoverageAudit $audit): int
    {
        try {
            $this->line(json_encode($audit->report($this->option('source-sha256') ?? ''), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
