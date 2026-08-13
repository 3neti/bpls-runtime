<?php

namespace App\Actions;

use App\Models\CollectionAllocation;
use App\Models\Receipt;

final class RenderReceiptPdf
{
    public function handle(Receipt $receipt): string
    {
        $receipt->loadMissing([
            'issuedBy',
            'treasuryCollection.receivedBy',
            'treasuryCollection.allocations.paymentScheduleLine.lineOfBusiness',
            'paymentSchedule',
            'permitApplication.business.owner',
            'assessment',
        ]);

        $document = new SimplePdfDocument(
            'Business Permit Receipt',
            $this->documentCode($receipt),
            'Business Permit and Licensing System',
            'Receipt numbering, void, reprint, and reconciliation policy remain unresolved.',
        );
        $page = $document->addPage('Receipt '.$receipt->receipt_number);
        $y = SimplePdfDocument::ContentTop;

        $document->rectangle($page, 42, $y - 78, 511, 78, 0.94);
        $document->text($page, 'RECEIPT NUMBER', 54, $y - 22, 8, true);
        $document->text($page, $receipt->receipt_number, 54, $y - 44, 16, true, monospace: true);
        $document->text($page, 'AMOUNT PAID', 382, $y - 22, 8, true);
        $document->text($page, $this->money($receipt->amount_cents), 541, $y - 45, 18, true, 'right');
        $document->text($page, 'Status: '.str($receipt->status->value)->replace('_', ' ')->title(), 54, $y - 64, 8);
        $document->text($page, 'Numbering: '.$receipt->numbering_authority, 382, $y - 64, 8);
        $y -= 108;

        $y = $this->section($document, $page, $y, 'Receipt Facts', [
            'Issued at' => $receipt->issued_at->toIso8601String(),
            'Issued by' => $receipt->issuedBy?->name ?? 'System',
            'Collection method' => str($receipt->treasuryCollection->method->value)->replace('_', ' ')->title()->toString(),
            'Collected at' => $receipt->treasuryCollection->received_at->toIso8601String(),
            'Collected by' => $receipt->treasuryCollection->receivedBy?->name ?? 'System',
            'Reference' => $receipt->treasuryCollection->reference_number ?? 'Not recorded',
        ]);

        $business = $receipt->permitApplication->business;
        $owner = $business->owner;
        $y = $this->section($document, $page, $y, 'Business / Payer', [
            'Business' => $business->name,
            'Trade name' => $business->trade_name ?? 'Not recorded',
            'Registration' => $business->registration_number ?? 'Not recorded',
            'Address' => trim(($business->address ?? 'No address').' '.($business->barangay ? 'Barangay '.$business->barangay : '')),
            'Payer' => $receipt->treasuryCollection->payer_name ?? $owner->name,
            'Owner' => $owner->name,
        ]);

        $y = $this->section($document, $page, $y, 'Application Context', [
            'Application' => $receipt->permitApplication->application_number ?? 'Application #'.$receipt->permitApplication->id,
            'Application type' => str($receipt->permitApplication->type->value)->replace('_', ' ')->title()->toString(),
            'Application year' => (string) $receipt->permitApplication->application_year,
            'Assessment' => 'Assessment #'.$receipt->assessment->sequence.' ('.$this->label($receipt->assessment->status->value).')',
            'Payment schedule' => 'Schedule #'.$receipt->paymentSchedule->sequence.' ('.$this->label($receipt->paymentSchedule->status->value).')',
        ]);

        $y = $this->allocations($document, $page, $y, $receipt);

        $this->section($document, $page, $y, 'Policy Gaps', [
            'Numbering' => 'Automatic receipt numbering authority remains unresolved.',
            'Document status' => 'This PDF is a generated receipt artifact, not the final official layout.',
            'Reconciliation' => 'Void, reprint, and reconciliation policy remain unresolved.',
        ]);

        return $document->render();
    }

    /**
     * @param  array<string, string>  $rows
     */
    private function section(SimplePdfDocument $document, int $page, float $y, string $title, array $rows): float
    {
        $document->text($page, strtoupper($title), 42, $y, 9, true);
        $y -= 18;

        foreach ($rows as $label => $value) {
            if ($y < SimplePdfDocument::ContentBottom + 30) {
                $page = $document->addPage($title.' continued');
                $y = SimplePdfDocument::ContentTop;
            }

            $document->text($page, $label, 54, $y, 7.5, true);
            $y = $document->wrappedText($page, $value, 170, $y, 370, 8.5, 11);
            $y -= 3;
        }

        return $y - 10;
    }

    private function allocations(SimplePdfDocument $document, int $page, float $y, Receipt $receipt): float
    {
        $document->text($page, 'ALLOCATIONS', 42, $y, 9, true);
        $y -= 18;
        $document->line($page, 42, $y + 7, 553, $y + 7, 0.6, 0.45);
        $document->text($page, 'Code', 54, $y, 7.5, true);
        $document->text($page, 'Item', 145, $y, 7.5, true);
        $document->text($page, 'Category', 370, $y, 7.5, true);
        $document->text($page, 'Amount', 541, $y, 7.5, true, 'right');
        $y -= 14;

        foreach ($receipt->treasuryCollection->allocations as $allocation) {
            if ($y < SimplePdfDocument::ContentBottom + 42) {
                $page = $document->addPage('Allocations continued');
                $y = SimplePdfDocument::ContentTop;
            }

            $this->allocation($document, $page, $y, $allocation);
            $y -= 28;
        }

        $document->line($page, 42, $y + 12, 553, $y + 12, 0.6, 0.45);
        $document->text($page, 'Total', 370, $y, 9, true);
        $document->text($page, $this->money($receipt->amount_cents), 541, $y, 9, true, 'right');

        return $y - 28;
    }

    private function allocation(SimplePdfDocument $document, int $page, float $y, CollectionAllocation $allocation): void
    {
        $line = $allocation->paymentScheduleLine;
        $document->text($page, $line->code, 54, $y, 7.5, monospace: true);
        $document->wrappedText($page, $line->name, 145, $y, 200, 7.5, 9);
        $document->text($page, str($line->category->value)->title()->toString(), 370, $y, 7.5);
        $document->text($page, $this->money($allocation->amount_cents), 541, $y, 7.5, align: 'right');
        $document->text($page, $line->lineOfBusiness?->name ?? 'Application-wide', 145, $y - 12, 7, false);
    }

    private function documentCode(Receipt $receipt): string
    {
        return 'receipt-'.$receipt->id.'-'.substr(hash('sha256', $receipt->receipt_number.'|'.$receipt->amount_cents.'|'.$receipt->issued_at->toIso8601String()), 0, 16);
    }

    private function money(int $amountCents): string
    {
        return 'PHP '.number_format($amountCents / 100, 2);
    }

    private function label(string $value): string
    {
        return str($value)->replace('_', ' ')->title()->toString();
    }
}
