<?php

namespace App\Actions;

use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use Illuminate\Support\Str;

final class RenderApplicationFormPdf
{
    public function handle(PermitApplication $permitApplication): string
    {
        $permitApplication->loadMissing([
            'business.owner',
            'lines.lineOfBusiness',
        ]);

        $document = new SimplePdfDocument(
            'Business Application Form Artifact',
            $this->documentCode($permitApplication),
            'Business Permit and Licensing System',
            'Application form artifact renders currently captured intake facts; full TOR field parity remains unresolved.',
        );
        $page = $document->addPage(Str::limit($this->applicationLabel($permitApplication), 46));
        $y = SimplePdfDocument::ContentTop;

        $document->rectangle($page, 42, $y - 88, 511, 88, 0.94);
        $document->text($page, 'APPLICATION', 54, $y - 22, 8, true);
        $document->wrappedText($page, $this->applicationLabel($permitApplication), 54, $y - 43, 320, 11, 13, true, true);
        $document->text($page, 'APPLICATION YEAR', 406, $y - 22, 8, true);
        $document->text($page, (string) $permitApplication->application_year, 541, $y - 45, 18, true, 'right');
        $document->text($page, 'Type: '.$this->label($permitApplication->type->value), 54, $y - 67, 8);
        $document->text($page, 'Status: '.$this->label($permitApplication->status->value), 406, $y - 67, 8);
        $y -= 118;

        $business = $permitApplication->business;
        $owner = $business->owner;
        $y = $this->section($document, $page, $y, 'Owner / Applicant', [
            'Owner name' => $owner->name,
            'Email' => $owner->email ?? 'Not recorded',
            'Phone' => $owner->phone ?? 'Not recorded',
            'Address' => $owner->address ?? 'Not recorded',
        ]);

        $y = $this->section($document, $page, $y, 'Business Information', [
            'Application number' => $this->applicationLabel($permitApplication),
            'Business name' => $business->name,
            'Trade name' => $business->trade_name ?? 'Not recorded',
            'Registration' => $business->registration_number ?? 'Not recorded',
            'Business address' => $business->address ?? 'Not recorded',
            'Barangay' => $business->barangay ?? 'Not recorded',
            'Submitted at' => $permitApplication->submitted_at?->toIso8601String() ?? 'Not recorded',
        ]);

        $y = $this->lines($document, $page, $y, $permitApplication);

        $this->section($document, $page, $y, 'Policy Gaps', [
            'Field parity' => 'TOR/regulatory fields beyond the current rescue intake remain unresolved.',
            'Attachments' => 'Documentary requirements, uploaded files, and checklist evidence are not yet represented in this artifact.',
            'Certification' => 'Applicant certification, sworn declaration, official receiving marks, and final municipal layout remain unresolved.',
            'Lifecycle scope' => 'Renewal, amendment, transfer, retirement, and PIL-specific fields remain unresolved where not already characterized.',
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

    private function lines(SimplePdfDocument $document, int $page, float $y, PermitApplication $permitApplication): float
    {
        $document->text($page, 'LINES OF BUSINESS', 42, $y, 9, true);
        $y -= 18;
        $document->line($page, 42, $y + 7, 553, $y + 7, 0.6, 0.45);
        $document->text($page, 'Code', 54, $y, 7.5, true);
        $document->text($page, 'Line of business', 145, $y, 7.5, true);
        $document->text($page, 'Gross sales', 394, $y, 7.5, true, 'right');
        $document->text($page, 'Capital', 482, $y, 7.5, true, 'right');
        $document->text($page, 'Qty', 541, $y, 7.5, true, 'right');
        $y -= 14;

        foreach ($permitApplication->lines as $line) {
            if ($y < SimplePdfDocument::ContentBottom + 42) {
                $page = $document->addPage('Lines of business continued');
                $y = SimplePdfDocument::ContentTop;
            }

            $this->line($document, $page, $y, $line);
            $y -= 30;
        }

        if ($permitApplication->lines->isEmpty()) {
            $document->text($page, 'No lines of business were recorded.', 54, $y, 8);
            $y -= 24;
        }

        return $y - 18;
    }

    private function line(SimplePdfDocument $document, int $page, float $y, PermitApplicationLine $line): void
    {
        $document->text($page, $line->lineOfBusiness?->code ?? 'N/A', 54, $y, 7.5, monospace: true);
        $document->wrappedText($page, $line->lineOfBusiness?->name ?? 'Unclassified', 145, $y, 210, 7.5, 9);
        $document->text($page, $this->money($line->declared_gross_sales_cents), 394, $y, 7.5, align: 'right');
        $document->text($page, $this->money($line->capital_investment_cents), 482, $y, 7.5, align: 'right');
        $document->text($page, (string) $line->quantity, 541, $y, 7.5, align: 'right');
    }

    private function documentCode(PermitApplication $permitApplication): string
    {
        return 'application-form-'.$permitApplication->id.'-'.substr(hash('sha256', $this->applicationLabel($permitApplication).'|'.$permitApplication->application_year.'|'.$permitApplication->updated_at?->toIso8601String()), 0, 16);
    }

    private function applicationLabel(PermitApplication $permitApplication): string
    {
        return $permitApplication->application_number ?? 'Application #'.$permitApplication->id;
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
