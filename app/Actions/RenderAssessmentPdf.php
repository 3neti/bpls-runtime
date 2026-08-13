<?php

namespace App\Actions;

use App\Models\Assessment;
use App\Models\AssessmentLine;

final class RenderAssessmentPdf
{
    public function handle(Assessment $assessment): string
    {
        $assessment->loadMissing([
            'assessedBy',
            'permitApplication.business.owner',
            'lines.lineOfBusiness',
        ]);

        $document = new SimplePdfDocument(
            'Assessment Sheet Artifact',
            $this->documentCode($assessment),
            'Business Permit and Licensing System',
            'Assessment artifact renders persisted line snapshots only; full ordinance catalog and rounding policy remain unresolved.',
        );
        $page = $document->addPage('Assessment #'.$assessment->sequence);
        $y = SimplePdfDocument::ContentTop;

        $document->rectangle($page, 42, $y - 88, 511, 88, 0.94);
        $document->text($page, 'ASSESSMENT', 54, $y - 22, 8, true);
        $document->text($page, 'Assessment #'.$assessment->sequence, 54, $y - 46, 16, true, monospace: true);
        $document->text($page, 'TOTAL ASSESSED', 382, $y - 22, 8, true);
        $document->text($page, $this->money($assessment->total_amount_cents), 541, $y - 45, 18, true, 'right');
        $document->text($page, 'Status: '.$this->label($assessment->status->value), 54, $y - 67, 8);
        $document->text($page, 'Assessed: '.($assessment->assessed_at?->toIso8601String() ?? 'Not recorded'), 382, $y - 67, 8);
        $y -= 118;

        $permitApplication = $assessment->permitApplication;
        $business = $permitApplication->business;
        $owner = $business->owner;
        $y = $this->section($document, $page, $y, 'Assessment Context', [
            'Application' => $permitApplication->application_number ?? 'Application #'.$permitApplication->id,
            'Application type' => $this->label($permitApplication->type->value),
            'Application year' => (string) $permitApplication->application_year,
            'Business' => $business->name,
            'Owner' => $owner->name,
            'Assessed by' => $assessment->assessedBy?->name ?? 'System',
        ]);

        $y = $this->assessmentLines($document, $page, $y, $assessment);

        $this->section($document, $page, $y, 'Policy Gaps', [
            'Calculation source' => 'This artifact renders persisted assessment lines and does not recalculate fees or taxes.',
            'Ordinance catalog' => 'Full Revenue Code fee/rate catalog extraction remains incomplete.',
            'Formula policy' => 'Formula semantics, rounding, PIL, surcharge, and renewal-specific rules remain unresolved where not already characterized.',
            'Document layout' => 'Official assessment-sheet layout, signatories, and final municipal branding remain unresolved.',
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

    private function assessmentLines(SimplePdfDocument $document, int $page, float $y, Assessment $assessment): float
    {
        $document->text($page, 'ASSESSMENT LINES', 42, $y, 9, true);
        $y -= 18;
        $document->line($page, 42, $y + 7, 553, $y + 7, 0.6, 0.45);
        $document->text($page, 'Code', 54, $y, 7.5, true);
        $document->text($page, 'Fee / Tax', 135, $y, 7.5, true);
        $document->text($page, 'Basis', 330, $y, 7.5, true);
        $document->text($page, 'Basis amount', 455, $y, 7.5, true, 'right');
        $document->text($page, 'Amount', 541, $y, 7.5, true, 'right');
        $y -= 14;

        foreach ($assessment->lines->sortBy('code')->values() as $line) {
            if ($y < SimplePdfDocument::ContentBottom + 48) {
                $page = $document->addPage('Assessment lines continued');
                $y = SimplePdfDocument::ContentTop;
            }

            $this->assessmentLine($document, $page, $y, $line);
            $y -= 42;
        }

        if ($assessment->lines->isEmpty()) {
            $document->text($page, 'No assessment lines were recorded.', 54, $y, 8);
            $y -= 24;
        }

        $document->line($page, 42, $y + 12, 553, $y + 12, 0.6, 0.45);
        $document->text($page, 'Total', 330, $y, 9, true);
        $document->text($page, $this->money($assessment->total_amount_cents), 541, $y, 9, true, 'right');

        return $y - 30;
    }

    private function assessmentLine(SimplePdfDocument $document, int $page, float $y, AssessmentLine $line): void
    {
        $document->text($page, $line->code, 54, $y, 7.5, monospace: true);
        $document->wrappedText($page, $line->name, 135, $y, 170, 7.5, 9);
        $document->text($page, $this->label($line->basis), 330, $y, 7.5);
        $document->text($page, $this->money($line->basis_amount_cents), 455, $y, 7.5, align: 'right');
        $document->text($page, $this->money($line->amount_cents), 541, $y, 7.5, align: 'right');
        $document->text($page, $this->label($line->category->value).' / '.$this->label($line->calculation_type->value), 135, $y - 12, 7);
        $document->text($page, $line->lineOfBusiness?->name ?? 'Application-wide', 135, $y - 23, 7);
    }

    private function documentCode(Assessment $assessment): string
    {
        return 'assessment-'.$assessment->id.'-'.substr(hash('sha256', $assessment->id.'|'.$assessment->sequence.'|'.$assessment->total_amount_cents.'|'.$assessment->assessed_at?->toIso8601String()), 0, 16);
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
