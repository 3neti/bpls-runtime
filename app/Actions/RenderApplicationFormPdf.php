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
        $this->page2Assessments($document, $projection['page_2_assessment'], $projection['computation_assessment_slip']);

        return $document->render();
    }

    /**
     * @param  array<string, mixed>  $page2
     * @param  array<string, mixed>|null  $assessmentSlip
     */
    private function page2Assessments(SimplePdfDocument $document, array $page2, ?array $assessmentSlip): void
    {
        $page = $document->addPage('Assessments');
        $y = SimplePdfDocument::ContentTop;

        $document->text($page, 'ASSESSMENTS', 42, $y, 14, true);
        $y -= 24;
        $document->text($page, 'OFFICE FEE DETERMINATIONS', 42, $y, 9, true);
        $document->text(
            $page,
            'Emerging total: '.$this->optionalMoney(data_get($page2, 'emerging_total_amount_cents')),
            553,
            $y,
            9,
            true,
            'right',
        );
        $y = $document->wrappedText($page, (string) data_get($page2, 'statement'), 42, $y - 16, 511, 8, 10);
        $y -= 12;

        $offices = data_get($page2, 'offices', []);
        if (! is_array($offices) || $offices === []) {
            $document->rectangle($page, 42, $y - 42, 511, 42, 0.96);
            $document->wrappedText($page, 'Awaiting the mandatory BPLO routing determination.', 54, $y - 17, 487, 9, 11, true);
            $y -= 60;
        } else {
            foreach ($offices as $office) {
                if (! is_array($office)) {
                    continue;
                }
                $lines = data_get($office, 'lines', []);
                $lineCount = is_array($lines) ? count($lines) : 0;
                $height = 55 + ($lineCount * 27) + (is_array(data_get($office, 'certification')) ? 30 : 22);
                if ($y - $height < SimplePdfDocument::ContentBottom) {
                    $page = $document->addPage('Office Fee Determinations continued');
                    $y = SimplePdfDocument::ContentTop;
                }

                $document->rectangle($page, 42, $y - $height, 511, $height, 0.98);
                $document->text($page, strtoupper((string) data_get($office, 'label')), 54, $y - 17, 9, true);
                $document->text(
                    $page,
                    strtoupper($this->label((string) data_get($office, 'status'))),
                    350,
                    $y - 17,
                    6.5,
                    true,
                );
                $document->text(
                    $page,
                    $this->money((int) data_get($office, 'total_amount_cents', 0)),
                    541,
                    $y - 17,
                    9,
                    true,
                    'right',
                );
                $document->text(
                    $page,
                    $this->paymentOrderCountLabel((int) data_get($office, 'payment_order_count', 0)),
                    54,
                    $y - 33,
                    7,
                );
                $lineY = $y - 52;
                foreach (is_array($lines) ? $lines : [] as $line) {
                    if (! is_array($line)) {
                        continue;
                    }
                    $document->wrappedText($page, (string) data_get($line, 'name'), 54, $lineY, 230, 7.5, 9, true);
                    $document->text($page, $this->label((string) data_get($line, 'status')), 300, $lineY, 7.5);
                    $document->text(
                        $page,
                        $this->optionalMoney(data_get($line, 'display_amount_cents')),
                        541,
                        $lineY,
                        7.5,
                        true,
                        'right',
                    );
                    $orderId = data_get($line, 'paperless_payment_order.id');
                    if (is_int($orderId)) {
                        $document->text($page, 'Paperless Payment Order #'.$orderId, 54, $lineY - 10, 6.5);
                    } elseif (data_get($line, 'display_amount_cents') !== null) {
                        $document->text($page, 'Proposed amount', 54, $lineY - 10, 6.5);
                    }
                    $lineY -= 27;
                }

                $certification = data_get($office, 'certification');
                if (is_array($certification)) {
                    $document->text($page, 'ELECTRONICALLY CERTIFIED BY', 54, $lineY, 6.5, true);
                    $document->wrappedText(
                        $page,
                        (string) data_get($certification, 'officer_name', 'Municipal officer').' - '.
                            (string) data_get($certification, 'certified_at'),
                        190,
                        $lineY,
                        351,
                        7,
                        9,
                    );
                } else {
                    $document->text($page, 'Office certification pending', 54, $lineY, 7, true);
                }
                $y -= $height + 10;
            }
        }

        if ($y < SimplePdfDocument::ContentBottom + 55) {
            $page = $document->addPage('Consolidated Assessment');
            $y = SimplePdfDocument::ContentTop;
        }
        $document->rectangle($page, 42, $y - 42, 511, 42, 0.94);
        $document->text($page, 'CONSOLIDATED ASSESSMENT', 54, $y - 17, 8, true);
        $document->text(
            $page,
            $this->optionalMoney(data_get($assessmentSlip, 'total_amount_cents', data_get($page2, 'emerging_total_amount_cents'))),
            541,
            $y - 17,
            10,
            true,
            'right',
        );
        $unresolved = (int) data_get($page2, 'required_unresolved_charge_count', 0);
        $document->text(
            $page,
            $unresolved > 0
                ? "Waiting for {$unresolved} required determination(s)."
                : ($assessmentSlip === null ? 'Ready for Assessment preparation.' : (string) data_get($assessmentSlip, 'statement')),
            54,
            $y - 32,
            7,
        );
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

    private function optionalMoney(mixed $amountCents): string
    {
        return is_int($amountCents) ? $this->money($amountCents) : 'Not yet available';
    }

    private function paymentOrderCountLabel(int $count): string
    {
        return $count.' current Paperless Payment '.($count === 1 ? 'Order' : 'Orders');
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
