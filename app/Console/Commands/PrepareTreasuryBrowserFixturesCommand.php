<?php

namespace App\Console\Commands;

use App\LifecycleScenarios\TreasuryBrowserFixtures;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bpls:treasury:prepare-browser-fixture {key} {--confirm-local-synthetic : Explicitly authorize one local synthetic fixture}')]
#[Description('Prepare an isolated synthetic application up to, but not through, Treasury classification.')]
class PrepareTreasuryBrowserFixturesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TreasuryBrowserFixtures $fixtures): int
    {
        if (! $this->option('confirm-local-synthetic')) {
            $this->error('Explicit --confirm-local-synthetic is required. No records created.');

            return self::FAILURE;
        }
        try {
            $this->line(json_encode($fixtures->prepare((string) $this->argument('key')), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
