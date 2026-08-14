<?php

namespace App\Actions;

use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use Illuminate\Support\Str;

final class RenderPermitPdf
{
    public function __construct(
        private readonly DescribePermitDocumentConfiguration $documentConfiguration,
        private readonly DescribePermitVerificationBoundary $verificationBoundary,
        private readonly DescribePermitReleaseReadiness $releaseReadiness,
    ) {}

    public function handle(PermitApplication $permitApplication): string
    {
        $documentConfiguration = $this->documentConfiguration->handle();
        $verificationBoundary = $this->verificationBoundary->handle($permitApplication);
        $releaseReadiness = $this->releaseReadiness->handle($permitApplication);

        $permitApplication->loadMissing([
            'business.owner',
            'lines.lineOfBusiness',
            'assessments' => fn ($query) => $query->latest(),
            'clearances' => fn ($query) => $query->with('completedBy')->orderBy('id'),
        ]);

        $document = new SimplePdfDocument(
            "Mayor's Permit Artifact",
            $this->documentCode($permitApplication),
            $documentConfiguration['municipality']['system_name'],
            'Permit release, QR verification, signatories, and clearance policy remain unresolved.',
        );
        $page = $document->addPage(Str::limit($this->applicationLabel($permitApplication), 46));
        $y = SimplePdfDocument::ContentTop;

        $document->rectangle($page, 42, $y - 88, 511, 88, 0.94);
        $document->text($page, 'PERMIT APPLICATION', 54, $y - 22, 8, true);
        $document->wrappedText($page, $this->applicationLabel($permitApplication), 54, $y - 43, 320, 11, 13, true, true);
        $document->text($page, 'APPLICATION YEAR', 406, $y - 22, 8, true);
        $document->text($page, (string) $permitApplication->application_year, 541, $y - 45, 18, true, 'right');
        $document->text($page, 'Type: '.$this->label($permitApplication->type->value), 54, $y - 67, 8);
        $document->text($page, 'Status: '.$this->label($permitApplication->status->value), 406, $y - 67, 8);
        $y -= 118;

        $business = $permitApplication->business;
        $owner = $business->owner;
        $y = $this->section($document, $page, $y, 'Business Permit Facts', [
            'Business' => $business->name,
            'Trade name' => $business->trade_name ?? 'Not recorded',
            'Registration' => $business->registration_number ?? 'Not recorded',
            'Business address' => trim(($business->address ?? 'No address').' '.($business->barangay ? 'Barangay '.$business->barangay : '')),
            'Owner' => $owner->name,
            'Owner contact' => trim(($owner->email ?? 'No email').' '.($owner->phone ? '/ '.$owner->phone : '')),
        ]);

        $latestAssessment = $permitApplication->assessments->first();
        $y = $this->section($document, $page, $y, 'Application Context', [
            'Submitted at' => $permitApplication->submitted_at?->toIso8601String() ?? 'Not recorded',
            'Latest assessment' => $latestAssessment === null
                ? 'No assessment recorded'
                : 'Assessment #'.$latestAssessment->sequence.' ('.$this->label($latestAssessment->status->value).') - '.$this->money($latestAssessment->total_amount_cents),
            'Document status' => 'Generated permit artifact; this route does not release or issue a permit.',
        ]);

        $y = $this->lines($document, $page, $y, $permitApplication);
        $y = $this->clearances($document, $page, $y, $permitApplication);
        $y = $this->signatories($document, $page, $y, $documentConfiguration);
        $y = $this->authorityBoundary($document, $page, $y, $releaseReadiness);
        $y = $this->verification($document, $page, $y, $verificationBoundary);

        $this->section($document, $page, $y, 'Policy Gaps', [
            'Release' => 'Permit issuance and release gating remain unresolved because source Released status precedes clearance completion.',
            'Clearances' => 'Clearance checklist evidence is represented for review only and does not release or issue a permit.',
            'Verification' => 'Public verification currently confirms artifact identity only; QR release verification remains unresolved.',
            'Signatories' => 'Signatory names and titles may be configured for rendering, but official authority and final municipal layout remain unresolved.',
        ]);

        return $document->render();
    }

    /**
     * @param  array{
     *     authority_boundary: array{
     *         status: string,
     *         software_knows: array<string, bool>,
     *         human_authority_decides: list<string>,
     *         software_records: list<string>,
     *         artifact_statement: string
     *     }
     * }  $releaseReadiness
     */
    private function authorityBoundary(SimplePdfDocument $document, int &$page, float $y, array $releaseReadiness): float
    {
        $boundary = $releaseReadiness['authority_boundary'];

        return $this->section($document, $page, $y, 'Authority Boundary', [
            'Boundary status' => $this->label($boundary['status']),
            'Software knows' => $this->booleanList($boundary['software_knows']),
            'Human authority decides' => $this->labelList($boundary['human_authority_decides']),
            'Software records after decision' => $this->labelList($boundary['software_records']),
            'Artifact statement' => $boundary['artifact_statement'],
        ]);
    }

    /**
     * @param  array<string, string>  $rows
     */
    private function section(SimplePdfDocument $document, int &$page, float $y, string $title, array $rows): float
    {
        if ($y < SimplePdfDocument::ContentBottom + 84) {
            $page = $document->addPage($title.' continued');
            $y = SimplePdfDocument::ContentTop;
        }

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

    private function lines(SimplePdfDocument $document, int &$page, float $y, PermitApplication $permitApplication): float
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
            $y -= 28;
        }

        if ($permitApplication->lines->isEmpty()) {
            $document->text($page, 'No lines of business were recorded.', 54, $y, 8);
            $y -= 24;
        }

        return $y - 18;
    }

    private function clearances(SimplePdfDocument $document, int &$page, float $y, PermitApplication $permitApplication): float
    {
        $document->text($page, 'CLEARANCE EVIDENCE', 42, $y, 9, true);
        $y -= 18;
        $document->line($page, 42, $y + 7, 553, $y + 7, 0.6, 0.45);
        $document->text($page, 'Clearance', 54, $y, 7.5, true);
        $document->text($page, 'Status', 285, $y, 7.5, true);
        $document->text($page, 'Completed by', 365, $y, 7.5, true);
        $document->text($page, 'Completed at', 541, $y, 7.5, true, 'right');
        $y -= 14;

        foreach ($permitApplication->clearances as $clearance) {
            if ($y < SimplePdfDocument::ContentBottom + 42) {
                $page = $document->addPage('Clearance evidence continued');
                $y = SimplePdfDocument::ContentTop;
            }

            $document->wrappedText($page, $clearance->label, 54, $y, 205, 7.5, 9);
            $document->text($page, $this->label($clearance->status->value), 285, $y, 7.5);
            $document->wrappedText($page, $clearance->completedBy?->name ?? 'Not completed', 365, $y, 115, 7.5, 9);
            $document->text($page, $clearance->completed_at?->toDateString() ?? 'Pending', 541, $y, 7.5, align: 'right');
            $y -= 28;
        }

        if ($permitApplication->clearances->isEmpty()) {
            $document->text($page, 'No clearance checklist evidence has been recorded.', 54, $y, 8);
            $y -= 24;
        }

        $document->wrappedText($page, 'Clearance completion evidence is informational in this artifact. Actual permit release remains blocked until issuance authority, signatories, QR verification, and legacy Released status semantics are resolved.', 54, $y, 470, 8, 10);

        return $y - 36;
    }

    /**
     * @param  array{
     *     municipality: array{name: string, province: string, system_name: string},
     *     permit_signatories: list<array{role: string, name: string, title: string, authority_status: string}>,
     *     authority_verified: bool,
     *     policy_note: string
     * }  $documentConfiguration
     */
    private function signatories(SimplePdfDocument $document, int &$page, float $y, array $documentConfiguration): float
    {
        $rows = [
            'Municipality' => $documentConfiguration['municipality']['name'].', '.$documentConfiguration['municipality']['province'],
        ];

        foreach ($documentConfiguration['permit_signatories'] as $signatory) {
            $rows[$signatory['role']] = $signatory['name'].' - '.$signatory['title'].' ('.$this->label($signatory['authority_status']).')';
        }

        $rows['Authority status'] = $documentConfiguration['authority_verified']
            ? 'All configured permit signatories are marked verified in application configuration.'
            : $documentConfiguration['policy_note'];

        return $this->section($document, $page, $y, 'Document Signatory Configuration', $rows);
    }

    /**
     * @param  array{
     *     reference: string,
     *     url: string,
     *     view_url: string,
     *     status: string,
     *     can_verify_release: bool,
     *     released: bool,
     *     policy_note: string
     * }  $verificationBoundary
     */
    private function verification(SimplePdfDocument $document, int &$page, float $y, array $verificationBoundary): float
    {
        return $this->section($document, $page, $y, 'Verification Boundary', [
            'Reference' => $verificationBoundary['reference'],
            'Public page' => $verificationBoundary['view_url'],
            'Verification API' => $verificationBoundary['url'],
            'Status' => $this->label($verificationBoundary['status']),
            'Policy note' => $verificationBoundary['policy_note'],
        ]);
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
        return 'permit-'.$permitApplication->id.'-'.substr(hash('sha256', $this->applicationLabel($permitApplication).'|'.$permitApplication->application_year.'|'.$permitApplication->updated_at?->toIso8601String()), 0, 16);
    }

    private function applicationLabel(PermitApplication $permitApplication): string
    {
        return $permitApplication->application_number ?? 'Application #'.$permitApplication->id;
    }

    private function money(int $amountCents): string
    {
        return 'PHP '.number_format($amountCents / 100, 2);
    }

    /**
     * @param  array<string, bool>  $values
     */
    private function booleanList(array $values): string
    {
        return collect($values)
            ->map(fn (bool $value, string $key): string => $this->label($key).': '.($value ? 'Yes' : 'No'))
            ->implode('; ');
    }

    /**
     * @param  list<string>  $values
     */
    private function labelList(array $values): string
    {
        return collect($values)
            ->map(fn (string $value): string => $this->label($value))
            ->implode('; ');
    }

    private function label(string $value): string
    {
        return str($value)->replace('_', ' ')->title()->toString();
    }
}
