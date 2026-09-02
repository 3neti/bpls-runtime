<?php

namespace App\Actions;

use App\Models\Assessment;

final class RenderAssessmentPdf
{
    public function __construct(private readonly BuildComputationAssessmentSlip $buildSlip) {}

    public function handle(Assessment $assessment): string
    {
        $slip = $this->buildSlip->handle($assessment);
        $document = new SimplePdfDocument(
            'Computation/Assessment Slip',
            'assessment-'.$assessment->id.'-'.substr($slip['reference']['snapshot_hash'], 0, 16),
            'Municipality of Ipil',
            'Executable projection of one immutable Assessment snapshot; no fee or quarterly allocation is calculated by the document.',
        );
        $page = $document->addPage('Computation/Assessment Slip');
        $y = SimplePdfDocument::ContentTop;

        $document->text($page, $slip['institution']['country'], 297, $y, 11, true, 'center');
        $document->text($page, $slip['institution']['province'], 297, $y - 15, 9, align: 'center');
        $document->text($page, strtoupper($slip['institution']['municipality']), 297, $y - 30, 11, true, 'center');
        $document->text($page, 'Reference: not officially assigned', 553, $y, 8, true, 'right');
        $document->text($page, 'Application: '.$assessment->permitApplication->application_number, 553, $y - 13, 8, align: 'right');
        $document->text($page, strtoupper($slip['institution']['title']), 297, $y - 62, 16, true, 'center');
        $document->line($page, 185, $y - 67, 409, $y - 67, 0.8, 0.1);
        $y -= 92;

        foreach ([
            'Transaction Type' => strtoupper($slip['transaction_type']),
            'Owner/Proprietor' => $slip['owner_proprietor'],
            'Name of Business' => $slip['business_name'],
            'Address of Business' => $slip['business_address'] ?? 'Not recorded',
            'Payment Mode' => strtoupper($slip['payment_mode']),
        ] as $label => $value) {
            $document->text($page, $label.':', 42, $y, 8.5);
            $document->wrappedText($page, $value, 145, $y, 405, 8.5, 10);
            $y -= 13;
        }

        $y -= 8;
        $document->text($page, 'LINE OF BUSINESS', 42, $y, 9, true);
        $y -= 15;
        foreach ($slip['line_of_businesses'] as $line) {
            $document->wrappedText($page, trim(($line['code'] ? $line['code'].' - ' : '').$line['name']), 54, $y, 490, 8, 10);
            $y -= 12;
        }

        $y -= 7;
        $document->text($page, 'COMPUTATIONS:', 42, $y, 10, true);
        $y -= 18;
        foreach ($slip['line_sections'] as $section) {
            if ($y < 150) {
                $page = $document->addPage('Computations continued');
                $y = SimplePdfDocument::ContentTop;
            }
            $document->text($page, strtoupper($section['line_of_business_name'] ?? 'Line of Business'), 42, $y, 9, true);
            $y -= 14;
            foreach ($section['charges'] as $charge) {
                $document->text($page, $charge['name'], 58, $y, 8);
                $document->text($page, $this->money($charge['amount_cents']), 553, $y, 8, align: 'right');
                $y -= 12;
            }
            $document->line($page, 58, $y + 6, 553, $y + 6, 0.6, 0.35);
            $document->text($page, 'SUBTOTAL', 58, $y, 8.5, true);
            $document->text($page, $this->money($section['subtotal_amount_cents']), 553, $y, 8.5, true, 'right');
            $y -= 20;
        }

        if ($slip['application_charges'] !== []) {
            $document->text($page, 'APPLICATION-WIDE CHARGES', 42, $y, 9, true);
            $y -= 14;
            foreach ($slip['application_charges'] as $charge) {
                $document->text($page, $charge['name'], 58, $y, 8);
                $document->text($page, $this->money($charge['amount_cents']), 553, $y, 8, align: 'right');
                $y -= 12;
            }
        }

        if ($y < 165) {
            $page = $document->addPage('Totals and schedule');
            $y = SimplePdfDocument::ContentTop;
        }
        $document->line($page, 42, $y + 8, 553, $y + 8, 1, 0.1);
        $document->text($page, 'GRAND TOTAL', 42, $y - 4, 13, true);
        $document->text($page, $this->money($slip['grand_total_amount_cents']), 553, $y - 4, 13, true, 'right');
        $document->text($page, 'IN WORDS: '.($slip['in_words'] ?? 'Not safely derivable'), 42, $y - 20, 8);
        $y -= 48;

        $document->text($page, 'SCHEDULE OF PAYMENTS', 42, $y, 11, true);
        $document->text($page, 'Mode of Payment: '.strtoupper($slip['schedule_of_payments']['payment_mode']), 42, $y - 16, 8.5);
        $y -= 36;
        $document->text($page, 'Section', 54, $y, 8, true);
        $document->text($page, 'Due Date', 170, $y, 8, true);
        $document->text($page, 'Amount', 405, $y, 8, true, 'right');
        $document->text($page, 'Balance', 553, $y, 8, true, 'right');
        $y -= 14;
        foreach ($slip['schedule_of_payments']['quarters'] as $quarter) {
            $document->text($page, $quarter['section'], 54, $y, 8, true);
            $document->text($page, '-', 170, $y, 8);
            $document->text($page, '-', 405, $y, 8, align: 'right');
            $document->text($page, '-', 553, $y, 8, align: 'right');
            $y -= 13;
        }
        $document->wrappedText($page, 'BLOCKED - MUNICIPAL FISCAL DECISION. No Q1-Q4 allocation formula is inferred from the specimen.', 42, $y - 2, 511, 7.5, 9);
        $y -= 35;

        $document->text($page, 'Prepared By:', 42, $y, 8.5);
        $document->text($page, $slip['prepared_by']['name'] ?? 'Not recorded', 120, $y, 8.5, true);
        $document->text($page, 'Approved By:', 42, $y - 25, 8.5);
        $document->text($page, $slip['approved_by']['name'] ?? 'Pending Municipal Treasurer decision', 120, $y - 25, 8.5, true);
        $document->text($page, 'Municipal Treasurer', 120, $y - 37, 7.5);
        $document->text($page, 'Acknowledged By: Not yet available', 330, $y, 8.5);
        $document->text($page, 'Date: -', 330, $y - 25, 8.5);

        return $document->render();
    }

    private function money(int $amountCents): string
    {
        return 'Php. '.number_format($amountCents / 100, 2);
    }
}
