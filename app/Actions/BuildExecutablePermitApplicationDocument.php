<?php

namespace App\Actions;

use App\Data\Application\ApplicationDataResolver;
use App\Models\PermitApplication;
use App\Models\User;

/** Compatibility adapter for the existing Page 1/Page 2 document surface. */
final class BuildExecutablePermitApplicationDocument
{
    public function __construct(private readonly ApplicationDataResolver $resolver) {}

    /** @return array<string, mixed> */
    public function handle(PermitApplication $permitApplication, ?User $viewer = null): array
    {
        $application = $this->resolver->resolve($permitApplication, $viewer)->toArray();
        $assessment = data_get($application, 'financial.assessment');
        $counterCheck = data_get($application, 'financial.treasury_counter_check');
        $decision = data_get($application, 'financial.treasurer_decision');
        $payment = data_get($application, 'payment', []);
        $collections = data_get($payment, 'collections', []);
        $collections = is_array($collections) ? $collections : [];
        $receipts = data_get($application, 'official_receipts', []);
        $receipts = is_array($receipts) ? $receipts : [];
        $permit = data_get($application, 'permit', []);
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
                    'recommending_approval' => null,
                ];
            }
        }

        return [
            'identity' => [...$application['identity'], 'tax_year' => $application['identity']['application_year']],
            'declaration' => [
                'state' => $application['declaration']['state'],
                'declared_at' => $application['declaration']['declared_at'],
                'snapshot_hash' => $application['declaration']['snapshot_hash'],
                'snapshot' => $application['declaration']['snapshot'],
            ],
            'verification' => $certifications,
            'routing' => $application['routing'],
            'page_2_assessment' => [
                'status' => $assessment !== null ? 'assessment_prepared' : (data_get($application, 'routing.status') === 'pending' ? 'awaiting_bplo_routing' : 'office_determinations_in_progress'),
                'statement' => $assessment !== null
                    ? 'The canonical Assessment has been prepared from completed municipal determinations.'
                    : (data_get($application, 'routing.status') === 'pending' ? 'Awaiting the mandatory BPLO routing determination.' : 'Page 2 is the living municipal processing projection.'),
                'populated_from_canonical_assessment' => $assessment !== null,
                'emerging_total_amount_cents' => $assessment['total_amount_cents'] ?? data_get($application, 'financial.evaluation.working_paper.grand_total_amount_cents'),
                'required_unresolved_charge_count' => (int) data_get($application, 'financial.evaluation.working_paper.required_unresolved_charge_count', 0),
                'offices' => $offices,
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
                'collection_count' => count($collections),
                'latest_collection' => $collections === [] ? null : $collections[array_key_last($collections)],
            ],
            'official_receipt_reference' => $receipts === [] ? null : $receipts[array_key_last($receipts)],
            'permit_reference' => [
                'state' => data_get($permit, 'state'),
                'permit_number' => data_get($permit, 'permit_number'),
                'issued_on' => data_get($permit, 'issued_on'),
                'valid_until' => data_get($permit, 'valid_until'),
                'official_receipt_number' => data_get($permit, 'official_receipt_number'),
                'verification_reference' => data_get($permit, 'verification.reference'),
            ],
            'permit' => [
                'status' => 'not_issued',
                'statement' => 'Permit not yet issued',
                'mayor_signature_authority' => data_get($application, 'permit.issuing_authority.authority_status'),
            ],
        ];
    }
}
