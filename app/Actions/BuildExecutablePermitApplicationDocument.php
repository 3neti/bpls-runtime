<?php

namespace App\Actions;

use App\Data\Application\ApplicationDataResolver;
use App\Models\PermitApplication;
use App\Models\User;

/** Compatibility adapter for the existing Page 1/Page 2 document surface. */
final class BuildExecutablePermitApplicationDocument
{
    public function __construct(
        private readonly ApplicationDataResolver $resolver,
        private readonly BuildConcernedOfficePaymentOrderSummary $paymentOrderSummary,
        private readonly ProjectCurrentAssessmentTotal $currentAssessmentTotal,
    ) {}

    /**
     * @param  array<string, mixed>|null  $resolvedApplication
     * @return array<string, mixed>
     */
    public function handle(
        PermitApplication $permitApplication,
        ?User $viewer = null,
        ?array $resolvedApplication = null,
    ): array {
        $application = $resolvedApplication ?? $this->resolver->resolve($permitApplication, $viewer)->toArray();
        $assessment = data_get($application, 'financial.assessment');
        $counterCheck = data_get($application, 'financial.treasury_counter_check');
        $decision = data_get($application, 'financial.treasurer_decision');
        $payment = data_get($application, 'payment', []);
        $collections = data_get($payment, 'collections', []);
        $collections = is_array($collections) ? $collections : [];
        $receipts = data_get($application, 'official_receipts', []);
        $receipts = is_array($receipts) ? $receipts : [];
        $paymentReconciliation = data_get($payment, 'reconciliation');
        $permit = data_get($application, 'permit', []);
        $syntheticLifecycle = data_get($permit, 'semantic_classification') === 'synthetic_only';
        $commissionedPath = data_get($permitApplication->metadata, 'nelson_reconciliation_v1.commissioned_path') === true;
        $officePaymentOrders = $commissionedPath
            ? $this->paymentOrderSummary->handle($permitApplication)
            : null;
        $projectedTotal = $assessment === null
            ? $this->currentAssessmentTotal->handle($permitApplication)
            : null;
        $displayedTotal = $assessment['total_amount_cents']
            ?? ($commissionedPath
                ? $projectedTotal
                : data_get($application, 'financial.evaluation.working_paper.grand_total_amount_cents'));
        $treasuryLines = data_get($application, 'business.treasury_assigned_lines_of_business', []);
        $treasuryLines = is_array($treasuryLines) ? $treasuryLines : [];
        $offices = [];
        $officePayloads = is_array($application['offices'] ?? null) ? $application['offices'] : [];
        foreach ($officePayloads as $office) {
            if (! is_array($office)) {
                continue;
            }
            $responsibilities = is_array($office['responsibilities'] ?? null) ? $office['responsibilities'] : [];
            $lines = [];
            $resolvedCount = 0;
            foreach ($responsibilities as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $resolved = ($item['resolution'] ?? null) === 'resolved';
                $resolvedCount += $resolved ? 1 : 0;
                $lines[] = [
                    'evaluation_item_id' => $item['id'],
                    'name' => $item['label'],
                    'status' => $resolved ? 'determined' : 'awaiting_determination',
                    'proposal_amount_cents' => $item['amount_cents'],
                    'determined_amount_cents' => $resolved ? $item['amount_cents'] : null,
                    'display_amount_cents' => $item['amount_cents'],
                    'source_classification' => null,
                    'paperless_payment_order' => null,
                ];
            }
            $offices[] = [
                'code' => $office['code'],
                'label' => $office['label'],
                'status' => $office['status'],
                'required_determination_count' => count($responsibilities),
                'resolved_determination_count' => $resolvedCount,
                'payment_order_count' => $office['paperless_payment_order_count'],
                'total_amount_cents' => $office['total_amount_cents'],
                'certification' => $office['certification'],
                'post_payment_certification' => $office['post_payment_certification'],
                'lines' => $lines,
            ];
        }
        $certificationPayloads = data_get($application, 'post_payment.certifications', []);
        $certifications = [];
        foreach (is_array($certificationPayloads) ? $certificationPayloads : [] as $certification) {
            if (is_array($certification)) {
                $certifications[] = [
                    'description' => $certification['label'],
                    'issuing_office' => $certification['code'],
                    'status' => $certification['status'],
                    'date_issued' => $certification['completed_at'],
                    'verified_by' => $certification['completed_by'],
                    'receipt_number' => $certification['receipt_number'],
                    'receipt_reviewed' => $certification['receipt_reviewed'],
                    'result' => $certification['result'],
                    'remarks' => $certification['remarks'],
                    'semantic_classification' => $certification['semantic_classification'],
                    'production_authority' => $certification['production_authority'],
                    'recommending_approval' => null,
                ];
            }
        }

        return [
            'commissioned_path' => $commissionedPath,
            'identity' => [...$application['identity'], 'tax_year' => $application['identity']['application_year']],
            'declaration' => [
                'state' => $application['declaration']['state'],
                'declared_at' => $application['declaration']['declared_at'],
                'snapshot_hash' => $application['declaration']['snapshot_hash'],
                'snapshot' => $application['declaration']['snapshot'],
            ],
            'signature_evidence' => $application['signature_evidence'],
            'verification' => $certifications,
            'routing' => $application['routing'],
            'page_2_assessment' => [
                'status' => $assessment !== null ? 'assessment_prepared' : (data_get($application, 'routing.status') === 'pending' ? 'awaiting_bplo_routing' : 'office_determinations_in_progress'),
                'statement' => $assessment !== null
                    ? 'The canonical Assessment has been prepared from completed municipal determinations.'
                    : (data_get($application, 'routing.status') === 'pending' ? 'Awaiting the mandatory BPLO routing determination.' : 'Page 2 is the living municipal processing projection.'),
                'populated_from_canonical_assessment' => $assessment !== null,
                'processing_summary' => $this->processingSummary(
                    commissionedPath: $commissionedPath,
                    routingStatus: (string) data_get($application, 'routing.status', 'pending'),
                    paymentOrders: $officePaymentOrders,
                    treasuryLineCount: count($treasuryLines),
                    assessment: is_array($assessment) ? $assessment : null,
                    decision: is_array($decision) ? $decision : null,
                    payment: is_array($payment) ? $payment : [],
                ),
                'total_label' => $assessment !== null ? 'Assessment total' : 'Current total',
                'total_source' => $assessment !== null
                    ? 'assessment'
                    : ($commissionedPath
                        ? ($projectedTotal !== null ? 'canonical_price_projection' : 'pending_canonical_inputs')
                        : 'evaluation_working_paper'),
                'emerging_total_amount_cents' => $displayedTotal,
                'required_unresolved_charge_count' => (int) data_get($application, 'financial.evaluation.working_paper.required_unresolved_charge_count', 0),
                'offices' => $offices,
                'concerned_office_payment_orders' => $officePaymentOrders,
                'treasury_lines_of_business' => $treasuryLines,
            ],
            'computation_assessment_slip' => $assessment === null ? null : [
                'assessment_id' => $assessment['id'],
                'sequence' => $assessment['sequence'],
                'status' => $assessment['status'],
                'total_amount_cents' => $assessment['total_amount_cents'],
                'line_count' => count(data_get($application, 'financial.price_report.components', [])),
                'statement' => 'Authoritative financial artifact: separate Computation/Assessment Slip',
            ],
            'treasury_counter_check' => $counterCheck,
            'municipal_treasurer' => $decision === null ? null : [...$decision, 'exact_approval' => $decision['action'] === 'approved'],
            'payment_reference' => [
                'state' => data_get($payment, 'state'),
                'payable' => data_get($payment, 'payable'),
                'payment_request' => data_get($payment, 'payment_request'),
                'reconciliation' => is_array($paymentReconciliation) ? $paymentReconciliation : null,
                'collection_count' => count($collections),
                'latest_collection' => $collections === [] ? null : $collections[array_key_last($collections)],
            ],
            'official_receipt_reference' => $receipts === [] ? null : $receipts[array_key_last($receipts)],
            'official_receipt_packet' => [
                'receipts' => $receipts,
                'total_receipted_minor' => (int) collect($receipts)->sum('total_amount_minor'),
                'receipt_count' => count($receipts),
                'required_receipt_group_count' => (int) data_get($paymentReconciliation, 'required_receipt_group_count', 0),
                'issued_receipt_group_count' => (int) data_get($paymentReconciliation, 'issued_receipt_group_count', 0),
                'unreceipted_amount_minor' => (int) data_get($paymentReconciliation, 'unreceipted_amount_cents', 0),
                'receipt_coverage_complete' => data_get($paymentReconciliation, 'receipt_coverage_complete') === true,
                'totals_reconciled' => data_get($paymentReconciliation, 'totals_reconciled') === true,
                'status' => (string) data_get($paymentReconciliation, 'status', 'awaiting_collection'),
            ],
            'permit_reference' => [
                'state' => data_get($permit, 'state'),
                'permit_number' => data_get($permit, 'permit_number'),
                'issued_on' => data_get($permit, 'issued_on'),
                'valid_until' => data_get($permit, 'valid_until'),
                'official_receipt_number' => data_get($permit, 'official_receipt_number'),
                'official_receipts' => data_get($permit, 'official_receipts', []),
                'verification_reference' => data_get($permit, 'verification.reference'),
            ],
            'permit' => [
                'status' => $syntheticLifecycle ? data_get($permit, 'state', 'blocked') : 'not_issued',
                'statement' => $syntheticLifecycle ? data_get($permit, 'statement', 'Permit not yet issued') : 'Permit not yet issued',
                'mayor_signature_authority' => data_get($application, 'permit.issuing_authority.authority_status'),
                ...($syntheticLifecycle ? ['production_authority' => false] : []),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $paymentOrders
     * @param  array<string, mixed>|null  $assessment
     * @param  array<string, mixed>|null  $decision
     * @param  array<string, mixed>  $payment
     */
    private function processingSummary(
        bool $commissionedPath,
        string $routingStatus,
        ?array $paymentOrders,
        int $treasuryLineCount,
        ?array $assessment,
        ?array $decision,
        array $payment,
    ): ?string {
        if (! $commissionedPath) {
            return null;
        }

        if ($routingStatus === 'pending' || $paymentOrders === null) {
            return 'Awaiting BPLO routing';
        }

        $finalizedOrders = (int) data_get($paymentOrders, 'finalized_office_count', 0);
        $requiredOrders = (int) data_get($paymentOrders, 'required_office_count', 0);
        if (data_get($paymentOrders, 'all_finalized') !== true) {
            return "Payment Orders · {$finalizedOrders} of {$requiredOrders}";
        }

        if ($treasuryLineCount === 0) {
            return "Awaiting Treasury classification · {$finalizedOrders} Payment Orders";
        }

        if ($assessment === null) {
            return "Ready for Assessment · {$finalizedOrders} Payment Orders · {$treasuryLineCount} Lines of Business";
        }

        $amount = $this->pesos((int) $assessment['total_amount_cents']);
        $paymentStatus = data_get($payment, 'payable.status');
        if ($paymentStatus === 'paid') {
            return "Payment complete · {$amount}";
        }
        if ($paymentStatus === 'partially_paid') {
            $paid = $this->pesos((int) data_get($payment, 'payable.paid_amount_cents', 0));

            return "Payment in progress · {$paid} of {$amount}";
        }

        if (data_get($decision, 'action') === 'approved') {
            return "Treasurer approved · {$amount}";
        }

        return "Assessment prepared · {$amount}";
    }

    private function pesos(int $amountCents): string
    {
        return '₱'.number_format($amountCents / 100, 2);
    }
}
