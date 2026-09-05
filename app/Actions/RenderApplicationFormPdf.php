<?php

namespace App\Actions;

use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use Illuminate\Support\Str;

final class RenderApplicationFormPdf
{
    public function __construct(
        private readonly BuildExecutablePermitApplicationDocument $buildExecutableDocument,
    ) {}

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
            'Ownership type' => $this->optionalLabel($business->ownership_type),
            'Organization/company' => $business->organization_name ?? 'Not recorded',
            'Occupancy' => $this->optionalLabel($business->occupancy),
            'Building' => $business->building_name ?? 'Not recorded',
            'Property index number' => $business->property_index_number ?? 'Not recorded',
            'Business area' => $business->business_area_square_meters === null ? 'Not recorded' : $business->business_area_square_meters.' square meters',
            'Employees' => $business->male_employee_count === null && $business->female_employee_count === null ? 'Not recorded' : 'Male '.($business->male_employee_count ?? 0).' / Female '.($business->female_employee_count ?? 0),
            'Business contact' => $business->contact_number ?? 'Not recorded',
            'Business email' => $business->email ?? 'Not recorded',
            'Established on' => $business->established_on?->toDateString() ?? 'Not recorded',
            'Operations started on' => $business->started_on?->toDateString() ?? 'Not recorded',
            'Registered on' => $business->registered_on?->toDateString() ?? 'Not recorded',
            'Submitted at' => $permitApplication->submitted_at?->toIso8601String() ?? 'Not recorded',
        ]);

        $y = $this->lines($document, $page, $y, $permitApplication);

        $this->section($document, $page, $y, 'Policy Gaps', [
            'Field parity' => 'Zoning, sanitary, local tax, regulatory, and fire-safety fields remain unresolved.',
            'Attachments' => 'Supporting documents are tracked separately; documentary checklist and sufficiency semantics remain unresolved.',
            'Certification' => 'Applicant certification, sworn declaration, official receiving marks, and final municipal layout remain unresolved.',
            'Lifecycle scope' => 'Renewal, amendment, transfer, retirement, and PIL-specific fields remain unresolved where not already characterized.',
        ]);
        $projection = $this->buildExecutableDocument->handle($permitApplication);
        $this->page2Continuation($document, $projection);

        return $document->render();
    }

    /** @param array<string, mixed> $projection */
    private function page2Continuation(SimplePdfDocument $document, array $projection): void
    {
        $page = $document->addPage('Page 2 - Municipal Processing');
        $y = SimplePdfDocument::ContentTop;
        $identity = data_get($projection, 'identity', []);
        $routing = data_get($projection, 'routing', []);
        $page2 = data_get($projection, 'page_2_assessment', []);
        $assessment = data_get($projection, 'computation_assessment_slip');
        $payment = data_get($projection, 'payment_reference', []);
        $receipt = data_get($projection, 'official_receipt_reference');
        $permit = data_get($projection, 'permit_reference', []);

        $document->text($page, 'APPLICATION FORM FOR BUSINESS PERMIT', 42, $y, 13, true);
        $document->text($page, 'PAGE 2', 553, $y, 9, true, 'right');
        $document->text($page, 'MUNICIPAL PROCESSING CONTINUATION SHEET', 42, $y - 18, 9, true);
        $document->line($page, 42, $y - 27, 553, $y - 27, 1.2, 0.08);
        $y -= 43;

        $this->processingFieldBand($document, $page, $y, [
            'OFFICIAL APPLICATION NO.' => (string) data_get($identity, 'application_number', ''),
            'TRACKING REFERENCE' => (string) data_get($identity, 'tracking_reference', ''),
            'TAX YEAR' => (string) data_get($identity, 'tax_year', ''),
            'TRANSACTION' => strtoupper($this->label((string) data_get($identity, 'type', ''))),
        ]);
        $y -= 48;

        $document->text($page, 'A. BPLO ROUTING', 42, $y, 8, true);
        $y -= 14;
        $this->processingTableHeader($document, $page, $y, ['CONCERNED OFFICE', 'BUSINESS CONTEXT', 'REQUIRED REVIEW']);
        $y -= 18;
        $works = data_get($routing, 'works', []);
        $visibleWorks = is_array($works) ? array_slice($works, 0, 4) : [];
        foreach ($visibleWorks as $work) {
            $this->processingTableRow($document, $page, $y, [
                (string) data_get($work, 'office_label', ''),
                (string) data_get($work, 'line_of_business_name', ''),
                (string) data_get($work, 'required_work', ''),
            ], 29);
            $y -= 29;
        }
        for ($blank = count($visibleWorks); $blank < 4; $blank++) {
            $this->processingTableRow($document, $page, $y, ['', '', ''], 29);
            $y -= 29;
        }
        $this->processingFieldBand($document, $page, $y, [
            'STATUS' => strtoupper($this->label((string) data_get($routing, 'status', ''))),
            'RECORDED BY' => (string) data_get($routing, 'determined_by', ''),
            'DATE' => (string) data_get($routing, 'determined_at', ''),
            'PAGE 1 DECLARATION' => data_get($projection, 'declaration.snapshot_hash') === null ? '' : 'FROZEN',
        ], 35);
        $y -= 49;

        $document->text($page, 'B. OFFICE DETERMINATIONS AND PAYMENT ORDERS', 42, $y, 8, true);
        $document->text($page, 'WORKING TOTAL '.$this->blankMoney(data_get($page2, 'emerging_total_amount_cents')), 553, $y, 8, true, 'right');
        $y -= 14;
        $this->processingTableHeader($document, $page, $y, ['OFFICE', 'DETERMINATIONS', 'PPOS']);
        $y -= 18;
        $offices = data_get($page2, 'offices', []);
        $visibleOffices = is_array($offices) ? array_slice($offices, 0, 4) : [];
        foreach ($visibleOffices as $office) {
            $this->processingTableRow($document, $page, $y, [
                (string) data_get($office, 'label', ''),
                data_get($office, 'resolved_determination_count', 0).'/'.data_get($office, 'required_determination_count', 0),
                (string) data_get($office, 'payment_order_count', ''),
            ], 22);
            $document->text($page, $this->blankMoney(data_get($office, 'total_amount_cents')), 541, $y - 14, 7, true, 'right');
            $y -= 22;
        }
        for ($blank = count($visibleOffices); $blank < 4; $blank++) {
            $this->processingTableRow($document, $page, $y, ['', '', ''], 22);
            $y -= 22;
        }
        $y -= 10;

        $document->text($page, 'C. ASSESSMENT REFERENCE', 42, $y, 8, true);
        $y -= 10;
        $this->processingFieldBand($document, $page, $y, [
            'ASSESSMENT NO.' => is_array($assessment) ? (string) data_get($assessment, 'sequence', '') : '',
            'STATUS' => is_array($assessment) ? strtoupper($this->label((string) data_get($assessment, 'status', ''))) : '',
            'ASSESSED AMOUNT' => is_array($assessment) ? $this->blankMoney(data_get($assessment, 'total_amount_cents')) : '',
            'UNRESOLVED CHARGES' => (int) data_get($page2, 'required_unresolved_charge_count', 0) > 0
                ? (string) data_get($page2, 'required_unresolved_charge_count')
                : '',
        ], 37);
        $y -= 51;

        $document->text($page, 'D. TREASURY VERIFICATION', 42, $y, 8, true);
        $y -= 10;
        $this->processingFieldBand($document, $page, $y, [
            'COUNTER-CHECK' => strtoupper($this->label((string) data_get($projection, 'treasury_counter_check.result', ''))),
            'DATE CHECKED' => (string) data_get($projection, 'treasury_counter_check.checked_at', ''),
            'TREASURER ACTION' => strtoupper($this->label((string) data_get($projection, 'municipal_treasurer.action', ''))),
            'DATE ACTED' => (string) data_get($projection, 'municipal_treasurer.decided_at', ''),
        ], 37);
        $y -= 51;

        $document->text($page, 'E. PAYMENT AND OFFICIAL RECEIPT REFERENCE', 42, $y, 8, true);
        $y -= 10;
        $this->processingFieldBand($document, $page, $y, [
            'PAYABLE STATUS' => strtoupper($this->label((string) data_get($payment, 'payable.status', ''))),
            'BALANCE' => $this->blankMoney(data_get($payment, 'payable.balance_amount_cents')),
            'OFFICIAL RECEIPT NO.' => is_array($receipt) ? (string) data_get($receipt, 'receipt_number', '') : '',
            'SERIES / DATE' => is_array($receipt)
                ? trim((string) data_get($receipt, 'series', '').' / '.(string) data_get($receipt, 'issued_on', ''), ' /')
                : '',
        ], 37);
        $y -= 51;

        $document->text($page, 'F. POST-PAYMENT CERTIFICATIONS', 42, $y, 8, true);
        $document->text($page, 'G. PERMIT PROCESSING REFERENCE', 300, $y, 8, true);
        $y -= 10;
        $certifications = data_get($projection, 'verification', []);
        $this->processingFieldBand($document, $page, $y, [
            'CERTIFICATIONS' => is_array($certifications) && $certifications !== [] ? (string) count($certifications) : '',
            'COMPLETED' => is_array($certifications)
                ? (string) collect($certifications)->where('status', 'completed')->count()
                : '',
            'PERMIT NO.' => (string) data_get($permit, 'permit_number', ''),
            'OR BINDING' => (string) data_get($permit, 'official_receipt_number', ''),
        ], 37);

        $detailRows = collect(is_array($offices) ? $offices : [])->flatMap(function (mixed $office): array {
            if (! is_array($office)) {
                return [];
            }

            $lines = data_get($office, 'lines', []);

            return collect(is_array($lines) ? $lines : [])->map(fn (mixed $line): array => [
                'office' => (string) data_get($office, 'label', ''),
                'responsibility' => (string) data_get($line, 'name', ''),
                'determination' => strtoupper($this->label((string) data_get($line, 'status', ''))),
                'ppo' => is_int(data_get($line, 'paperless_payment_order.id'))
                    ? '#'.data_get($line, 'paperless_payment_order.id')
                    : '',
                'amount' => $this->blankMoney(data_get($line, 'display_amount_cents')),
            ])->all();
        })->values();

        foreach ($detailRows->chunk(8) as $sheetIndex => $rows) {
            $page = $document->addPage('Page 2-'.chr(65 + $sheetIndex));
            $y = SimplePdfDocument::ContentTop;
            $document->text($page, 'OFFICE DETERMINATION AND PPO REGISTER', 42, $y, 12, true);
            $document->text($page, 'PAGE 2-'.chr(65 + $sheetIndex), 553, $y, 9, true, 'right');
            $document->text($page, (string) data_get($identity, 'tracking_reference', ''), 42, $y - 17, 7, monospace: true);
            $y -= 36;
            $this->processingTableHeader($document, $page, $y, ['OFFICE', 'RESPONSIBILITY', 'DETERMINATION']);
            $document->text($page, 'PPO', 438, $y - 12, 6, true);
            $document->text($page, 'AMOUNT', 541, $y - 12, 6, true, 'right');
            $y -= 18;
            foreach ($rows as $row) {
                $this->processingTableRow($document, $page, $y, [
                    (string) data_get($row, 'office', ''),
                    (string) data_get($row, 'responsibility', ''),
                    (string) data_get($row, 'determination', ''),
                ], 58);
                $document->text($page, (string) data_get($row, 'ppo', ''), 438, $y - 18, 7);
                $document->text($page, (string) data_get($row, 'amount', ''), 541, $y - 18, 7, true, 'right');
                $y -= 58;
            }
            for ($blank = $rows->count(); $blank < 8; $blank++) {
                $this->processingTableRow($document, $page, $y, ['', '', ''], 58);
                $y -= 58;
            }
        }
    }

    /** @param array<string, string> $fields */
    private function processingFieldBand(SimplePdfDocument $document, int $page, float $y, array $fields, float $height = 42): void
    {
        $width = 511 / max(1, count($fields));
        foreach (array_values($fields) as $index => $value) {
            $x = 42 + ($width * $index);
            $document->rectangle($page, $x, $y - $height, $width, $height, 0.2, false);
            $document->text($page, (string) array_keys($fields)[$index], $x + 6, $y - 11, 5.5, true);
            $document->wrappedText($page, $value, $x + 6, $y - 24, $width - 12, 7, 8, true);
        }
    }

    /** @param list<string> $labels */
    private function processingTableHeader(SimplePdfDocument $document, int $page, float $y, array $labels): void
    {
        $this->processingTableRow($document, $page, $y, $labels, 18, true);
    }

    /** @param list<string> $values */
    private function processingTableRow(SimplePdfDocument $document, int $page, float $y, array $values, float $height, bool $header = false): void
    {
        $widths = [150, 190, 171];
        $x = 42;
        foreach ($values as $index => $value) {
            $width = $widths[$index] ?? 171;
            $document->rectangle($page, $x, $y - $height, $width, $height, $header ? 0.91 : 0.2, $header);
            if (! $header) {
                $document->rectangle($page, $x, $y - $height, $width, $height, 0.2, false);
            }
            $document->wrappedText($page, $value, $x + 6, $y - ($header ? 12 : 14), $width - 12, $header ? 6 : 7, $header ? 7 : 8, $header);
            $x += $width;
        }
    }

    private function blankMoney(mixed $amountCents): string
    {
        return is_int($amountCents) ? $this->money($amountCents) : '';
    }

    /**
     * @param  array<string, string>  $rows
     */
    private function section(SimplePdfDocument $document, int &$page, float $y, string $title, array $rows): float
    {
        $document->text($page, strtoupper($title), 42, $y, 9, true);
        $y -= 18;

        foreach ($rows as $label => $value) {
            if ($y < SimplePdfDocument::ContentBottom + 30) {
                $page = $document->addPage($title.' continued');
                $y = SimplePdfDocument::ContentTop;
            }

            $document->text($page, $label, 54, $y, 7.5, true);
            $y = $document->wrappedText($page, $value, 170, $y, 370, 8.5, 10);
            $y -= 1;
        }

        return $y - 10;
    }

    private function lines(SimplePdfDocument $document, int &$page, float $y, PermitApplication $permitApplication): float
    {
        $document->text($page, 'LINES OF BUSINESS', 42, $y, 9, true);
        $y -= 18;
        $document->line($page, 42, $y + 7, 553, $y + 7, 0.6, 0.45);
        $document->text($page, 'Code', 54, $y, 7.5, true);
        $document->text($page, 'Line of business', 230, $y, 7.5, true);
        $document->text($page, 'Gross sales', 394, $y, 7.5, true, 'right');
        $document->text($page, 'Capital', 482, $y, 7.5, true, 'right');
        $document->text($page, 'Qty', 541, $y, 7.5, true, 'right');
        $y -= 14;

        foreach ($permitApplication->lines as $line) {
            if ($y < SimplePdfDocument::ContentBottom + 46) {
                $page = $document->addPage('Lines of business continued');
                $y = SimplePdfDocument::ContentTop;
            }

            $this->line($document, $page, $y, $line);
            $y -= 36;
        }

        if ($permitApplication->lines->isEmpty()) {
            $document->text($page, 'No lines of business were recorded.', 54, $y, 8);
            $y -= 24;
        }

        return $y - 18;
    }

    private function line(SimplePdfDocument $document, int $page, float $y, PermitApplicationLine $line): void
    {
        $document->wrappedText($page, $line->lineOfBusiness->code, 54, $y, 160, 6.5, 8, monospace: true);
        $document->wrappedText($page, $line->lineOfBusiness->name, 230, $y, 140, 7, 8);
        $document->text($page, 'Started: '.($line->started_on?->toDateString() ?? 'Not recorded'), 230, $y - 26, 6.5);
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

    private function optionalLabel(?string $value): string
    {
        return $value === null ? 'Not recorded' : $this->label($value);
    }
}
