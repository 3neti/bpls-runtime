<?php

namespace App\Console\Commands;

use App\Actions\AssertDisposableProductLabEnvironment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bpls:product-lab:preflight {--json : Emit JSON}')]
#[Description('Refuse destructive product-lab setup unless the runtime and database are positively local and synthetic-only.')]
class PreflightBplsProductLabCommand extends Command
{
    public function handle(AssertDisposableProductLabEnvironment $preflight): int
    {
        $result = $preflight->handle();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info('PRODUCT LAB SAFETY GATE: PASS');
        $this->line('Environment: '.$result['environment']);
        $this->line('Database: '.$result['driver'].' · '.$result['database']);
        $this->line('Safety profile: '.$result['safety_profile'].' · synthetic only · production integrations disabled');

        return self::SUCCESS;
    }
}
