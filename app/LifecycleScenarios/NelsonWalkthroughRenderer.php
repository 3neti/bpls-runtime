<?php

namespace App\LifecycleScenarios;

class NelsonWalkthroughRenderer
{
    /**
     * @param  array<string, mixed>  $manifest
     */
    public function presenterScript(array $manifest): string
    {
        $resources = $manifest['resources'];

        return implode("\n", [
            '# Nelson Walkthrough Presenter Script',
            '',
            'Run ID: `'.$manifest['run_id'].'`',
            '',
            '## Screen Order',
            '',
            '1. Citizen applications: `'.$resources['list_url'].'`',
            '   Show the exact tracking reference, then open the prepared application.',
            '2. Citizen application detail: `'.$resources['detail_url'].'`',
            '   Point out the submitted application, supporting document, assessment, payment, receipt, clearances, and timeline.',
            '3. Citizen payment detail: `'.$resources['payment_detail_url'].'`',
            '   Show the paid schedule and receipted over-the-counter collection. Online payment remains unavailable.',
            '4. Staff permit detail: `'.$resources['permit_application_url'].'`',
            '   Show the same record, its assessment, clearances, generated artifact, and Ready for Authority Review boundary.',
            '5. Payment schedule: `'.$resources['payment_schedule_url'].'`',
            '   Show the persisted schedule and exact collection allocation.',
            '6. Receipt: `'.$resources['receipt_url'].'`',
            '   Show the issued manual receipt and its source records.',
            '7. Municipality configuration: `/staff/municipality-configuration`',
            '   Explain that configured officials and document associations are visible evidence, not issuance or legal authority.',
            '8. Public verification: `'.$resources['permit_verification_view_url'].'`',
            '   Show artifact identity, then emphasize that release and legal effect are deliberately not asserted.',
            '9. Migration evidence: `walkthrough/migration-evidence.html`',
            '   Present the production snapshot, Golden Financial Specimen, exact rehearsal totals, and quarantined identity frontier.',
            '',
            '## Close',
            '',
            'The replacement can execute and prove the municipal workflow through authority review. It deliberately refuses to claim legal release or effect until the Municipality supplies accepted authority.',
            '',
            '## Credentials',
            '',
            'Credentials are prepared at runtime by `php artisan lifecycle:prepare-nelson-walkthrough`. No password is stored in this package.',
        ])."\n";
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function summaryMarkdown(array $evidence): string
    {
        $history = $evidence['historical_evidence'];

        return implode("\n", [
            '# What Nelson Is Seeing',
            '',
            'This walkthrough follows one new business-permit application from a citizen draft through municipal review, assessment, payment scheduling, Treasury collection, receipt, clearances, and a generated permit artifact.',
            '',
            'The same application is shown from the citizen and municipal staff views. Every visible amount, status, document, and clearance is checked afterward against the application database and audit evidence.',
            '',
            'The application reaches **Ready for Authority Review**. The system deliberately does not call it released or legally effective because the Municipality has not yet supplied the accepted issuance and release authority needed for those claims.',
            '',
            'The migration segment shows that the authorized legacy production snapshot is preserved unchanged, and that Golden Financial Specimen `CAL-2026-001` can be traced across legal, operational, software, and historical evidence.',
            '',
            'The migration machinery has successfully rehearsed '.number_format($history['application_count']).' exact historical applications, '.number_format($history['schedule_count']).' schedules, '.number_format($history['fee_line_count']).' fee lines, and '.number_format($history['completed_payment_count']).' completed payments. It preserved PHP '.$this->money($history['scheduled_amount_cents']).' scheduled and PHP '.$this->money($history['paid_amount_cents']).' paid, then rolled everything back exactly without changing operational finance.',
            '',
            $evidence['identity_frontier']['summary'],
            '',
            'Configured official, authorized signatory, permit issuance authority, and legal effect remain separate municipal facts. The walkthrough shows those boundaries instead of hiding them.',
        ])."\n";
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function summaryHtml(array $evidence): string
    {
        return $this->page(
            'What Nelson Is Seeing',
            '<p class="lede">One permit application, followed from citizen submission to municipal authority review.</p>'.
            '<div class="band"><strong>Operationally proven</strong><span>Citizen intake, documents, assessment, payment schedule, OTC collection, receipt, clearances, permit artifact, public artifact verification, and canonical audit.</span></div>'.
            '<div class="band caution"><strong>Deliberately not asserted</strong><span>Official numbering, legal issuance, release, validity, and legal effect remain subject to accepted municipal authority.</span></div>'.
            '<h2>What the evidence proves</h2>'.
            '<p>The citizen and municipal staff views show the same application. The post-run audit checks the visible status, amounts, documents, collection, receipt, clearances, and authority boundary against the database.</p>'.
            '<p>The production snapshot remains unchanged. '.$this->escape($evidence['identity_frontier']['summary']).'</p>'.
            '<p class="authority">Configured official &ne; authorized signatory &ne; permit issuance authority &ne; legal effect.</p>',
        );
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function migrationEvidenceHtml(array $evidence): string
    {
        $history = $evidence['historical_evidence'];
        $rehearsalPhases = $history['rehearsal_phases'];

        if (! is_array($rehearsalPhases)) {
            throw new \InvalidArgumentException('Walkthrough rehearsal phases must be an array.');
        }

        $phases = '';

        foreach ($rehearsalPhases as $phase => $status) {
            if (! is_string($phase) || ! is_string($status)) {
                throw new \InvalidArgumentException('Walkthrough rehearsal phase names and statuses must be strings.');
            }

            $phases .= '<li><span>'.$this->escape(str($phase)->replace('_', ' ')->title()->toString()).'</span><strong>'.strtoupper($this->escape($status)).'</strong></li>';
        }

        return $this->page(
            'Migration Evidence',
            '<p class="lede">Production history is being preserved exactly, reversibly, and without turning uncertainty into municipal authority.</p>'.
            '<section data-testid="nelson-production-snapshot" data-status="immutable"><div class="eyebrow">Production Ground Zero</div><h2>'.$this->escape($evidence['snapshot']['label']).'</h2><p>'.$this->escape($evidence['snapshot']['summary']).'</p></section>'.
            '<section data-testid="nelson-calibration" data-reference="CAL-2026-001"><div class="eyebrow">Golden Financial Specimen</div><h2>CAL-2026-001</h2><p>'.$this->escape($evidence['calibration']['summary']).'</p></section>'.
            '<section data-testid="nelson-historical-evidence" data-application-count="'.$history['application_count'].'" data-schedule-count="'.$history['schedule_count'].'" data-fee-line-count="'.$history['fee_line_count'].'" data-completed-payment-count="'.$history['completed_payment_count'].'" data-unpaid-schedule-count="'.$history['unpaid_schedule_count'].'" data-scheduled-amount-cents="'.$history['scheduled_amount_cents'].'" data-paid-amount-cents="'.$history['paid_amount_cents'].'" data-operational-mutation-count="'.$history['operational_financial_mutation_count'].'">'.
            '<div class="eyebrow">Exact Historical Evidence</div><div class="metrics">'.
            $this->metric('Applications', $history['application_count']).
            $this->metric('Schedules', $history['schedule_count']).
            $this->metric('Fee lines', $history['fee_line_count']).
            $this->metric('Completed payments', $history['completed_payment_count']).
            $this->metric('Unpaid schedules', $history['unpaid_schedule_count']).
            $this->metric('Scheduled', '&#8369;'.$this->money($history['scheduled_amount_cents']), raw: true).
            $this->metric('Paid', '&#8369;'.$this->money($history['paid_amount_cents']), raw: true).
            $this->metric('Operational records changed', $history['operational_financial_mutation_count']).
            '</div><p>'.$this->escape($history['summary']).'</p><ul class="phases">'.$phases.'</ul></section>'.
            '<section data-testid="nelson-identity-frontier" data-exact-count="'.$evidence['identity_frontier']['exact_application_count'].'" data-reconciliation-count="'.$evidence['identity_frontier']['reconciliation_required_count'].'"><div class="eyebrow">Identity Frontier</div><h2>Exact where proven. Quarantined where unresolved.</h2><p>'.$this->escape($evidence['identity_frontier']['summary']).'</p></section>'.
            '<p class="authority">Migration preserves what happened. It does not invent identity, policy, or legal authority.</p>',
        );
    }

    private function metric(string $label, int|string $value, bool $raw = false): string
    {
        $rendered = is_int($value) ? number_format($value) : $value;

        return '<div><span>'.$this->escape($label).'</span><strong>'.($raw ? $rendered : $this->escape((string) $rendered)).'</strong></div>';
    }

    private function money(int $amountCents): string
    {
        return number_format($amountCents / 100, 2);
    }

    private function escape(string $value): string
    {
        return e($value);
    }

    private function page(string $title, string $content): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$this->escape($title).'</title><style>
*{box-sizing:border-box}body{margin:0;background:#f5f7f8;color:#172126;font-family:Arial,sans-serif;letter-spacing:0}main{max-width:1120px;margin:0 auto;padding:44px 36px 64px}h1{font-size:38px;line-height:1.08;margin:0 0 12px}h2{font-size:20px;margin:4px 0 10px}.lede{font-size:19px;line-height:1.55;max-width:800px;color:#425159;margin:0 0 30px}.eyebrow{font-size:12px;font-weight:700;text-transform:uppercase;color:#1f6b52;margin-bottom:8px}section,.band{background:#fff;border:1px solid #d9e0e3;border-left:4px solid #2b7a60;padding:22px;margin:16px 0}.band{display:grid;grid-template-columns:210px 1fr;gap:20px}.band.caution{border-left-color:#a56a00}.band span,section p{line-height:1.55;color:#425159}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1px;background:#d9e0e3;border:1px solid #d9e0e3;margin:18px 0}.metrics div{background:#fff;padding:16px;min-width:0}.metrics span{display:block;font-size:12px;color:#66757c;margin-bottom:7px}.metrics strong{font-size:19px;overflow-wrap:anywhere}.phases{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;padding:0;margin:18px 0 0;list-style:none}.phases li{display:flex;justify-content:space-between;gap:8px;background:#edf7f2;padding:10px;font-size:12px}.phases strong{color:#166534}.authority{font-weight:700;border-top:2px solid #172126;padding-top:18px;margin-top:28px}@media(max-width:760px){main{padding:24px 16px}h1{font-size:30px}.metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.phases{grid-template-columns:1fr 1fr}.band{grid-template-columns:1fr;gap:8px}}
</style></head><body><main><h1>'.$this->escape($title).'</h1>'.$content.'</main></body></html>';
    }
}
