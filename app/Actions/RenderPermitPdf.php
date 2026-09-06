<?php

namespace App\Actions;

use App\Data\Application\ApplicationDataResolver;
use App\Data\Application\BusinessPermitData;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\PostPaymentOfficeCertification;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class RenderPermitPdf
{
    public function __construct(
        private readonly DescribePermitDocumentConfiguration $documentConfiguration,
        private readonly DescribePermitVerificationBoundary $verificationBoundary,
        private readonly DescribePermitReleaseReadiness $releaseReadiness,
        private readonly ApplicationDataResolver $applicationDataResolver,
    ) {}

    public function handle(PermitApplication $permitApplication): string
    {
        $documentConfiguration = $this->documentConfiguration->handle();
        $verificationBoundary = $this->verificationBoundary->handle($permitApplication);
        $releaseReadiness = $this->releaseReadiness->handle($permitApplication);
        $permit = $this->applicationDataResolver->resolve($permitApplication)->permit;

        $permitApplication->loadMissing([
            'business.owner',
            'lines.lineOfBusiness',
            'assessments' => fn ($query) => $query->latest(),
            'postPaymentOfficeCertifications' => fn ($query) => $query->with(['certifiedBy', 'receipt'])->orderBy('id'),
        ]);

        if ($permit->semantic_classification === 'synthetic_only' && $permit->issued) {
            return $this->renderIssuedSyntheticPermit($permitApplication, $permit);
        }

        $document = new SimplePdfDocument(
            $permit->semantic_classification === 'synthetic_only' ? 'Business Permit · Synthetic Specimen' : "Mayor's Permit Preview",
            $this->documentCode($permitApplication),
            $documentConfiguration['municipality']['system_name'],
            $permit->statement,
        );
        $page = $document->addPage(Str::limit($this->applicationLabel($permitApplication), 46));
        $y = SimplePdfDocument::ContentTop;

        $document->rectangle($page, 42, $y - 88, 511, 88, 0.94);
        $document->text($page, $permit->semantic_classification === 'synthetic_only' ? 'BUSINESS PERMIT' : 'PERMIT APPLICATION', 54, $y - 22, 8, true);
        $document->wrappedText(
            $page,
            $permit->semantic_classification === 'synthetic_only' ? ($permit->permit_number ?? 'NOT YET ISSUED') : $this->applicationLabel($permitApplication),
            54,
            $y - 43,
            320,
            $permit->semantic_classification === 'synthetic_only' ? 15 : 11,
            $permit->semantic_classification === 'synthetic_only' ? 16 : 13,
            true,
            true,
        );
        $document->text($page, $permit->semantic_classification === 'synthetic_only' ? 'PERMIT YEAR' : 'APPLICATION YEAR', 406, $y - 22, 8, true);
        $document->text($page, (string) $permitApplication->application_year, 541, $y - 45, 18, true, 'right');
        $document->text($page, $permit->semantic_classification === 'synthetic_only' ? 'Issued: '.($permit->issued_on ?? 'Pending').'  Valid until: '.($permit->valid_until ?? 'Pending') : 'Type: '.$this->label($permitApplication->type->value), 54, $y - 67, 8);
        $document->text($page, $permit->semantic_classification === 'synthetic_only' ? 'State: '.$this->label($permit->state) : 'Status: '.$this->label($permitApplication->status->value), 406, $y - 67, 8);
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
            'Official Receipt' => $permit->official_receipt_bound
                ? trim(($permit->official_receipt_series ? $permit->official_receipt_series.' / ' : '').$permit->official_receipt_number)
                : 'Not bound - permit remains invalid and unreleased',
            'Document status' => $permit->semantic_classification === 'synthetic_only'
                ? $permit->statement
                : 'Generated preview document; this does not issue or release a permit.',
        ]);

        $y = $this->lines($document, $page, $y, $permitApplication);
        $y = $this->clearances($document, $page, $y, $permitApplication);
        $y = $this->signatories($document, $page, $y, $documentConfiguration);
        $y = $this->previewStatus($document, $page, $y, $releaseReadiness);
        $y = $this->verification($document, $page, $y, $verificationBoundary);

        return $document->render();
    }

    private function renderIssuedSyntheticPermit(PermitApplication $permitApplication, BusinessPermitData $permit): string
    {
        $document = new SimplePdfDocument(
            'Business Permit - Synthetic Specimen',
            $this->documentCode($permitApplication),
            'Municipality of Ipil',
            $permit->statement,
        );
        $page = $document->addBarePage();
        $assetPath = resource_path('pdf/ipil-business-permit');
        $document->jpeg($page, $assetPath.'/security-pattern.jpg', 0, 0, SimplePdfDocument::PageWidth, SimplePdfDocument::PageHeight);
        $document->jpeg($page, $assetPath.'/header.jpg', 0, 758, SimplePdfDocument::PageWidth, 78);
        $document->jpeg($page, $assetPath.'/footer.jpg', 0, 0, SimplePdfDocument::PageWidth, 160);

        $year = (string) $permitApplication->application_year;
        $document->coloredText($page, Str::substr($year, 0, 2), 298, 488, 176, true, 'center', false, 0.78, 0.80, 0.87);
        $document->coloredText($page, Str::substr($year, 2, 2), 298, 315, 176, true, 'center', false, 0.93, 0.78, 0.79);

        $document->coloredText($page, 'BUSINESS PERMIT', 298, 724, 29, true, 'center', false, 0.92, 0.03, 0.05);
        $document->coloredRectangle($page, 54, 694, 487, 18, 0.92, 0.03, 0.05);
        $document->coloredText($page, 'LABORATORY SPECIMEN - NOT FOR OFFICIAL USE', 298, 699, 9, true, 'center', false, 1, 1, 1);

        $this->permitIdentityHeader($document, $page, $permit);
        $document->text($page, 'This is to certify that permission is hereby granted to', 298, 627, 11, align: 'center');

        $y = 594.0;
        $y = $this->permitFact($document, $page, 'Name of Business', Str::upper($permit->business_name), $y, 11.5, true);
        $y = $this->permitFact($document, $page, 'Name of Owner/Operator', Str::upper($permit->owner_operator), $y, 10.5, true);
        $y = $this->permitFact($document, $page, 'Business Address', Str::upper($permit->business_address ?? 'NOT RECORDED'), $y, 8.5);

        $linesOfBusiness = $permitApplication->lines
            ->map(fn (PermitApplicationLine $line): string => Str::upper(
                ($line->line_of_business_id === null ? 'N/A' : $line->lineOfBusiness->code)
                .'- '.($line->line_of_business_id === null
                    ? (string) data_get($line->metadata, 'line_of_business_name', 'UNRESOLVED')
                    : $line->lineOfBusiness->name),
            ))
            ->implode(', ');
        $lineSize = match (true) {
            mb_strlen($linesOfBusiness) > 900 => 5.8,
            mb_strlen($linesOfBusiness) > 500 => 6.6,
            mb_strlen($linesOfBusiness) > 250 => 7.4,
            default => 8.5,
        };
        $y = $this->permitFact($document, $page, 'Line of Business', $linesOfBusiness === '' ? 'NOT RECORDED' : $linesOfBusiness, $y, $lineSize);

        $y -= 8;
        $jurisdiction = 'To operate and conduct business within the jurisdiction of the Municipality of Ipil, Zamboanga Sibugay, subject to existing laws, ordinances, rules and regulations.';
        $y = $document->wrappedText($page, $jurisdiction, 36, $y, 523, 8.8, 11);
        $y -= 8;
        $document->text($page, 'CONDITIONS', 36, $y, 10, true);
        $y -= 17;
        foreach ($permit->conditions as $index => $condition) {
            $document->text($page, ($index + 1).'.', 53, $y, 7.8);
            $nextY = $document->wrappedText($page, $condition, 72, $y, 474, 7.8, 9.5);
            $y = $nextY - 3;
        }

        $issuedOn = $permit->issued_on === null ? null : Carbon::parse($permit->issued_on);
        $issuanceSentence = $issuedOn === null
            ? 'Issuance date pending.'
            : sprintf(
                'Issued this %s day of %s at Ipil, Zamboanga Sibugay.',
                $this->ordinal($issuedOn->day),
                $issuedOn->format('F Y'),
            );
        $document->text($page, $issuanceSentence, 298, max(242, $y - 5), 8.8, align: 'center');

        $document->coloredText($page, 'SYNTHETIC AUTHORIZATION REFERENCE - NO MAYORAL SIGNATURE APPLIED', 298, 222, 6.6, true, 'center', false, 0.68, 0.08, 0.10);
        $document->text($page, 'HON. '.Str::upper($permit->issuing_authority['name'] ?? 'UNVERIFIED MUNICIPAL MAYOR'), 345, 202, 10, true, 'center');
        $document->text($page, 'Municipal Mayor', 345, 190, 8.5, align: 'center');
        $document->text($page, (string) ($permit->issuing_authority['signature_reference'] ?? 'Synthetic reference pending'), 345, 180, 5.8, align: 'center', monospace: true);

        $receiptSeries = $permit->official_receipt_series ?? 'Series of '.$year;
        $document->text($page, 'OR. No.:', 225, 164, 8.5);
        $document->text($page, $permit->official_receipt_number ?? 'NOT BOUND', 272, 164, 9, true);
        $document->text($page, $receiptSeries, 334, 164, 8.5);

        $this->verificationQr($document, $page, $permit->verification['view_url'], 43, 85, 68);
        $document->text($page, 'SCAN TO VERIFY IDENTITY', 77, 76, 6.5, true, 'center');
        $document->wrappedText(
            $page,
            'NOTE: This specimen must be displayed only for laboratory review. It is invalid without the Official Receipt number shown here. QR verification resolves this exact synthetic Permit identity only and does not establish legal effect or production authority.',
            128,
            137,
            335,
            7.2,
            9,
        );
        $document->text($page, 'Identity: '.$permit->verification['reference'].' | State: '.$this->label($permit->state), 128, 91, 6.2, true);

        return $document->render();
    }

    private function permitIdentityHeader(
        SimplePdfDocument $document,
        int $page,
        BusinessPermitData $permit,
    ): void {
        $columns = [
            ['x' => 114.0, 'label' => 'BUSINESS PERMIT NO.', 'value' => $permit->permit_number ?? 'NOT YET ISSUED'],
            ['x' => 298.0, 'label' => 'DATE ISSUED', 'value' => $permit->issued_on === null ? 'PENDING' : Str::upper(Carbon::parse($permit->issued_on)->format('F d, Y'))],
            ['x' => 480.0, 'label' => 'VALID UNTIL', 'value' => $permit->valid_until === null ? 'PENDING' : Carbon::parse($permit->valid_until)->format('F d, Y')],
        ];

        foreach ($columns as $column) {
            $document->coloredRectangle($page, $column['x'] - 78, 661, 156, 17, 0.92, 0.03, 0.05);
            $document->coloredText($page, $column['label'], $column['x'], 666, 8, true, 'center', false, 1, 1, 1);
            $document->text($page, $column['value'], $column['x'], 644, 10.2, true, 'center');
        }

        $document->coloredLine($page, 36, 637, 559, 637, 1.1, 0.92, 0.03, 0.05);
    }

    private function permitFact(
        SimplePdfDocument $document,
        int $page,
        string $label,
        string $value,
        float $y,
        float $size,
        bool $bold = false,
    ): float {
        $document->text($page, $label, 181, $y, 8, align: 'right');
        $lines = $document->wrap($value, 365, $size);
        foreach ($lines as $line) {
            $document->text($page, $line, 188, $y, $size, $bold);
            $y -= $size + 1.8;
        }
        $document->coloredLine($page, 185, $y + $size, 559, $y + $size, 0.7, 0.92, 0.03, 0.05);

        return $y - 6;
    }

    private function verificationQr(SimplePdfDocument $document, int $page, string $value, float $x, float $y, float $size): void
    {
        $matrix = Encoder::encode($value, ErrorCorrectionLevel::M(), Encoder::DEFAULT_BYTE_MODE_ENCODING)->getMatrix();
        $quietZone = 4;
        $moduleSize = $size / ($matrix->getWidth() + ($quietZone * 2));
        $document->coloredRectangle($page, $x, $y, $size, $size, 1, 1, 1);

        for ($row = 0; $row < $matrix->getHeight(); $row++) {
            for ($column = 0; $column < $matrix->getWidth(); $column++) {
                if ($matrix->get($column, $row) !== 1) {
                    continue;
                }

                $document->coloredRectangle(
                    $page,
                    $x + (($column + $quietZone) * $moduleSize),
                    $y + $size - (($row + $quietZone + 1) * $moduleSize),
                    $moduleSize,
                    $moduleSize,
                    0,
                    0,
                    0,
                );
            }
        }
    }

    private function ordinal(int $day): string
    {
        $suffix = match (true) {
            $day % 100 >= 11 && $day % 100 <= 13 => 'TH',
            $day % 10 === 1 => 'ST',
            $day % 10 === 2 => 'ND',
            $day % 10 === 3 => 'RD',
            default => 'TH',
        };

        return $day.$suffix;
    }

    /** @param array<string, mixed> $releaseReadiness */
    private function previewStatus(SimplePdfDocument $document, int &$page, float $y, array $releaseReadiness): float
    {
        $boundary = $releaseReadiness['authority_boundary'];

        return $this->section($document, $page, $y, 'Preview Status', [
            'Current availability' => $this->label($boundary['status']),
            'Municipal release' => 'Not confirmed',
            'Legal effect' => 'Not legally effective',
            'Note' => $releaseReadiness['reason'],
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

        $certifications = $permitApplication->postPaymentOfficeCertifications->isNotEmpty()
            ? $permitApplication->postPaymentOfficeCertifications
            : $permitApplication->clearances;
        $permitApplication->loadMissing('clearances.completedBy');
        foreach ($certifications as $clearance) {
            if ($y < SimplePdfDocument::ContentBottom + 42) {
                $page = $document->addPage('Clearance evidence continued');
                $y = SimplePdfDocument::ContentTop;
            }

            if ($clearance instanceof PostPaymentOfficeCertification) {
                $label = $clearance->office_label;
                $status = $clearance->result === null ? $clearance->status : $clearance->result;
                $actorName = $clearance->certified_by_id === null ? 'Not completed' : $clearance->certifiedBy->name;
                $date = $clearance->certified_at;
            } else {
                $label = $clearance->label;
                $status = $clearance->status->value;
                $actorName = $clearance->completed_by_id === null ? 'Not completed' : $clearance->completedBy->name;
                $date = $clearance->completed_at;
            }
            $document->wrappedText($page, $label, 54, $y, 205, 7.5, 9);
            $document->text($page, $this->label($status), 285, $y, 7.5);
            $document->wrappedText($page, $actorName, 365, $y, 115, 7.5, 9);
            $document->text($page, $date?->toDateString() ?? 'Pending', 541, $y, 7.5, align: 'right');
            $y -= 28;
        }

        if ($certifications->isEmpty()) {
            $document->text($page, 'Routing-derived post-payment certifications are pending.', 54, $y, 8);
            $y -= 24;
        }

        $document->wrappedText(
            $page,
            $permitApplication->postPaymentOfficeCertifications->isNotEmpty()
                ? 'Every certification shown is synthetic-only cleanroom evidence derived from actual BPLO routing. It does not assert production office or issuance authority.'
                : 'Clearance completion is shown for review. This preview document does not confirm permit issuance or municipal release; the responsible authority, signatories, public verification, and existing release records still require confirmation.',
            54,
            $y,
            470,
            8,
            10,
        );

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

        return $this->section($document, $page, $y, 'Signatory Details', $rows);
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
        return $this->section($document, $page, $y, 'Document Reference', [
            'Reference' => $verificationBoundary['reference'],
            'Public check page' => $verificationBoundary['view_url'],
            'Status' => $verificationBoundary['status'] === 'artifact_only'
                ? 'Preview document only'
                : $this->label($verificationBoundary['status']),
            'Note' => $verificationBoundary['policy_note'],
        ]);
    }

    private function line(SimplePdfDocument $document, int $page, float $y, PermitApplicationLine $line): void
    {
        $document->text($page, $line->line_of_business_id === null ? 'N/A' : $line->lineOfBusiness->code, 54, $y, 7.5, monospace: true);
        $document->wrappedText($page, $line->line_of_business_id === null ? 'Unclassified' : $line->lineOfBusiness->name, 145, $y, 210, 7.5, 9);
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

    private function label(string $value): string
    {
        return str($value)->replace('_', ' ')->title()->toString();
    }
}
