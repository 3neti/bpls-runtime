<?php

namespace App\Console\Commands;

use App\Actions\BuildBplsProductLabGuide;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

#[Signature('bpls:product-lab:inspect {--json : Emit JSON}')]
#[Description('Verify the canonical product-lab chronology and print the Board browser inspection guide.')]
class InspectBplsProductLabCommand extends Command
{
    public function handle(BuildBplsProductLabGuide $buildGuide): int
    {
        $guide = $buildGuide->handle();

        if ($this->option('json')) {
            $this->line(json_encode($guide, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('BPLS PRODUCT LAB READY');
        $this->newLine();
        $this->line('Institution');
        $this->line('  '.$guide['institution']['name']);
        $this->line('  Price List                         '.$guide['institution']['price_list']);
        $this->line('  Business Inspection Fee           '.$this->pesos($guide['institution']['business_inspection_fee_cents']).'/year · governed');
        $this->line('  Synthetic prices published        '.$guide['institution']['synthetic_prices_published']);
        $this->newLine();
        $this->line('Citizen');
        $this->line('  '.$guide['citizen']['name'].' (#'.$guide['citizen']['id'].')');
        $this->line('Municipal Owner');
        $this->line('  '.$guide['owner']['name'].' (#'.$guide['owner']['id'].')');
        $this->line('Business');
        $this->line('  '.$guide['business']['name'].' (#'.$guide['business']['id'].')');
        $this->newLine();
        $this->line('Permit history');
        foreach ($guide['applications'] as $application) {
            $this->line('  '.$application['year'].' · '.$application['type'].' (#'.$application['id'].')');
            $this->line('    Assessment                       '.$this->pesos($application['assessment_cents']));
            $this->line('    Municipal Treasurer              Approved exact Assessment');
            $this->line('    Amount Due                       '.$this->pesos($application['amount_due_cents']));
        }
        $this->newLine();
        $this->line('Inventory');
        $this->line('  Business Owners                    '.$guide['inventory']['business_owners']);
        $this->line('  Businesses                         '.$guide['inventory']['businesses']);
        $this->line('  Permit Applications                '.$guide['inventory']['permit_applications']);
        $this->newLine();
        $this->line('Browser inspection');
        foreach ($guide['links'] as $label => $url) {
            $this->line('  '.str($label)->replace('_', ' ')->headline()->padRight(34).' '.$url);
        }
        $this->newLine();
        $this->line('Notes');
        foreach ($guide['notes'] as $note) {
            $this->line('  - '.$note);
        }
        $this->newLine();
        $this->info('PRODUCT LAB READY');

        return self::SUCCESS;
    }

    private function pesos(int $amountCents): string
    {
        return '₱'.Number::format($amountCents / 100, precision: 2);
    }
}
