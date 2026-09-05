<?php

namespace App\Actions;

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

        $profile = data_get($receipt->source_snapshot, 'official_receipt_profile');
        $profile = is_array($profile) ? $profile : app(ResolveOfficialReceiptProfile::class)->handle();
        $document = new SimplePdfDocument(
            'Business Permit Receipt — Official Receipt AF No. 51',
            $this->documentCode($receipt),
            (string) data_get($profile, 'header.municipality'),
            'Automatic receipt numbering authority remains unresolved. Void, reprint, and reconciliation policy remain unresolved.',
        );
        $page = $document->addPage('AF No. 51');
        $left = 82.0;
        $right = 513.0;
        $width = $right - $left;
        $top = 744.0;
        $document->rectangle($page, $left, 108, $width, $top - 108, 0.15, false);
        $document->text($page, 'OFFICIAL RECEIPT', 297.5, 718, 17, true, 'center');
        $document->text($page, (string) data_get($profile, 'header.republic'), 297.5, 703, 9, false, 'center');
        $document->text($page, mb_strtoupper((string) data_get($profile, 'header.province')), 297.5, 687, 11, true, 'center');
        $document->text($page, (string) data_get($profile, 'header.office'), 297.5, 673, 9, false, 'center');
        $document->text($page, (string) data_get($profile, 'header.municipality'), 297.5, 650, 10, true, 'center');
        $document->text($page, $receipt->permitApplication->business->name, 297.5, 640, 7, false, 'center');
        $document->line($page, $left, 636, $right, 636);
        $document->text($page, 'Accountable Form No. '.data_get($profile, 'form.accountable_form_number', 51), 96, 616, 10, true);
        $document->text($page, '('.data_get($profile, 'form.revision').')', 96, 603, 8);
        $document->text($page, (string) data_get($profile, 'form.copy_designation', 'ORIGINAL'), 410, 616, 12, true, 'center');
        $receiptNumberSize = max(8.0, min(17.0, 350 / max(1, mb_strlen($receipt->receipt_number) * 0.6)));
        $document->text($page, $receipt->receipt_number, 410, 589, $receiptNumberSize, true, 'center', true);
        $document->line($page, $left, 574, $right, 574);
        $document->text($page, 'DATE', 94, 558, 8, true);
        $document->text($page, $receipt->issued_at->format('F j, Y'), 155, 558, 9);
        $document->line($page, $left, 545, $right, 545);
        $document->text($page, 'AGENCY', 94, 529, 8, true);
        $document->text($page, (string) data_get($receipt->source_snapshot, 'af51.agency', data_get($profile, 'defaults.agency')), 150, 529, 9);
        $document->text($page, 'FUND', 365, 529, 8, true);
        $document->text($page, (string) data_get($receipt->source_snapshot, 'af51.fund', data_get($profile, 'defaults.fund')), 410, 529, 9);
        $document->line($page, $left, 516, $right, 516);
        $document->text($page, 'PAYOR', 94, 500, 8, true);
        $document->text($page, $receipt->treasuryCollection->payer_name ?? $receipt->permitApplication->business->owner->name, 150, 500, 9);
        $document->line($page, $left, 486, $right, 486);
        $document->text($page, 'NATURE OF COLLECTION', 190, 469, 8, true, 'center');
        $document->text($page, 'ACCOUNT CODE', 375, 469, 8, true, 'center');
        $document->text($page, 'AMOUNT', 468, 469, 8, true, 'center');
        $document->line($page, $left, 454, $right, 454);
        $document->line($page, 330, 486, 330, 286);
        $document->line($page, 420, 486, 420, 286);
        $rowY = 438;
        foreach ($receipt->treasuryCollection->allocations->take(8) as $allocation) {
            $document->wrappedText($page, $allocation->paymentScheduleLine->name, 94, $rowY, 226, 8, 9);
            $document->text($page, (string) data_get($allocation->source_snapshot, 'account_code', data_get($allocation->source_snapshot, 'code', $allocation->paymentScheduleLine->code)), 375, $rowY, 5, false, 'center');
            $document->text($page, number_format($allocation->amount_cents / 100, 2), 501, $rowY, 8, false, 'right');
            $document->line($page, $left, $rowY - 12, $right, $rowY - 12, 0.4, 0.55);
            $rowY -= 21;
        }
        $document->line($page, $left, 286, $right, 286);
        $document->text($page, 'TOTAL', 290, 268, 11, true, 'right');
        $document->text($page, 'PHP '.number_format($receipt->amount_cents / 100, 2), 501, 268, 10, true, 'right');
        $document->line($page, $left, 252, $right, 252);
        $document->text($page, 'AMOUNT IN WORDS', 94, 236, 8, true);
        $document->wrappedText($page, (string) data_get($receipt->source_snapshot, 'af51.amount_in_words', $this->money($receipt->amount_cents).' ONLY'), 94, 221, 407, 8, 10);
        $document->line($page, $left, 194, $right, 194);
        $method = $receipt->treasuryCollection->method->value;
        $document->text($page, ($method === 'cash' ? '[X]' : '[ ]').' Cash', 96, 178, 8);
        $document->text($page, ($method === 'check' ? '[X]' : '[ ]').' Check', 96, 164, 8);
        $document->text($page, ($method === 'money_order' ? '[X]' : '[ ]').' Money Order', 96, 150, 8);
        $document->text($page, ($method === 'qr_ph' ? '[X]' : '[ ]').' QR Ph', 96, 136, 8);
        $document->text($page, 'NUMBER / REFERENCE', 230, 178, 7, true);
        $document->wrappedText($page, $receipt->treasuryCollection->reference_number ?? 'Not recorded', 230, 162, 145, 8, 10, false, true);
        $document->text($page, 'DATE', 395, 178, 7, true);
        $document->text($page, $receipt->treasuryCollection->received_at->format('Y-m-d'), 395, 162, 8);
        $document->text($page, (string) data_get($receipt->source_snapshot, 'issuer.printed_name', data_get($profile, 'collecting_officer.name')), 496, 146, 9, true, 'right');
        $document->text($page, (string) data_get($receipt->source_snapshot, 'issuer.printed_title', data_get($profile, 'collecting_officer.title')), 496, 133, 8, false, 'right');
        $document->text($page, (string) data_get($receipt->source_snapshot, 'issuer.printed_designation', data_get($profile, 'collecting_officer.designation')), 496, 120, 8, false, 'right');
        if (data_get($receipt->source_snapshot, 'official_receipt_profile.collecting_officer.authority_status') !== 'verified') {
            $document->text($page, (string) data_get($profile, 'laboratory_watermark'), 297.5, 96, 8, true, 'center');
        }

        return $document->render();
    }

    private function documentCode(Receipt $receipt): string
    {
        return 'receipt-'.$receipt->id.'-'.substr(hash('sha256', $receipt->receipt_number.'|'.$receipt->amount_cents.'|'.$receipt->issued_at->toIso8601String()), 0, 16);
    }

    private function money(int $amountCents): string
    {
        return 'PHP '.number_format($amountCents / 100, 2);
    }
}
