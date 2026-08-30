<?php

namespace App\Console\Commands;

use App\Actions\InspectBplsInstallation;
use App\Actions\InstallBplsBaseline;
use App\Enums\UserPermission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

#[Signature('bpls:install {--check : Inspect the installed baseline without changing any state} {--json : Emit only the machine-readable manifest}')]
#[Description('Establish or verify the Municipality of Ipil BPLS institutional operating baseline.')]
class InstallBplsCommand extends Command
{
    public function handle(InstallBplsBaseline $install, InspectBplsInstallation $inspect): int
    {
        $manifest = $this->option('check') ? $inspect->handle() : $install->handle();

        if ($this->option('json')) {
            $this->line(json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return $manifest['integrity']['pass'] ? self::SUCCESS : self::FAILURE;
        }

        $this->renderSummary($manifest, (bool) $this->option('check'));

        return $manifest['integrity']['pass'] ? self::SUCCESS : self::FAILURE;
    }

    /** @param array<string, mixed> $manifest */
    private function renderSummary(array $manifest, bool $checkOnly): void
    {
        $this->info($checkOnly ? 'BPLS INSTALLATION CHECK' : 'BPLS INSTALLATION BASELINE');
        $this->line($manifest['municipality']['name'].' · '.$manifest['municipality']['system_name']);
        $this->newLine();
        $this->line('MUNICIPAL PRICE LIST');
        $this->line('In Force');
        foreach ($manifest['price_list']['in_force'] as $charge) {
            $this->line('  '.$charge['name'].' · '.$this->pesos($charge['amount_cents']).' / '.$charge['cadence'].' · Used by Assessment');
            $this->line('  '.$charge['code'].' · '.$charge['legal_basis']);
        }
        $this->line('Recorded — municipal confirmation required · '.count($manifest['price_list']['recorded_confirmation_required']));
        foreach ($manifest['price_list']['recorded_confirmation_required'] as $charge) {
            $this->line('  '.$charge['name'].' · exact automatic charge blocked');
        }
        $this->line('Concerned-office determined · '.implode(', ', $manifest['price_list']['determined_during_municipal_evaluation']));
        $this->line('Synthetic/UAT exact pricing published · '.$manifest['price_list']['synthetic_uat_exact_published_count']);
        $this->line('Price List coherence · '.($manifest['price_list']['coherent'] ? 'PASS' : 'FAIL'));
        $this->line('Assessment pricing parity · '.($manifest['price_list']['assessment_parity']['pass'] ? 'PASS' : 'FAIL'));
        $this->newLine();
        $this->line('INSTITUTION');
        $this->line('Capabilities · '.count($manifest['roles']).' roles · '.count(UserPermission::cases()).' permissions');
        $this->line('Authority seats · '.count($manifest['positions']).' · named assignments remain separate');
        $this->line('Super User · '.$manifest['commissioning_administrator']['provisioning_status'].' · administrative envelope, not a municipal office');
        $this->newLine();
        $this->line('TRANSACTIONAL STATE');
        foreach ($manifest['zero_state']['counts'] as $name => $count) {
            $this->line('  '.str($name)->replace('_', ' ')->title().' · '.$count);
        }
        $this->line('Zero permit transactions · '.($manifest['zero_state']['is_empty'] ? 'PASS' : 'NO — existing transaction history preserved'));
        $this->newLine();
        $this->line('COMMISSIONING STATUS');
        foreach ($manifest['commissioning'] as $name => $status) {
            $this->line('  '.str($name)->replace('_', ' ')->title().' · '.$status);
        }
        $this->line('Installation fingerprint · '.$manifest['fingerprints']['installation_sha256']);
        $this->line('Price List fingerprint · '.$manifest['fingerprints']['price_list_sha256']);
        $this->line($checkOnly ? 'Read-only check · PASS' : 'Manifest · '.$manifest['evidence']['manifest_path']);

        foreach ($manifest['integrity']['issues'] as $issue) {
            $this->error($issue);
        }
    }

    private function pesos(int $amountCents): string
    {
        return 'PHP '.Number::format($amountCents / 100, precision: 2);
    }
}
