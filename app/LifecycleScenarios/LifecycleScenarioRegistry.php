<?php

namespace App\LifecycleScenarios;

use InvalidArgumentException;

final class LifecycleScenarioRegistry
{
    /**
     * @return array<string, LifecycleScenarioDefinition>
     */
    public function all(): array
    {
        return [
            'citizen_new_permit_lifecycle_authority_boundary' => new LifecycleScenarioDefinition(
                key: 'citizen_new_permit_lifecycle_authority_boundary',
                label: 'Citizen-originated new permit lifecycle to authority boundary',
                mode: 'citizen_new_permit_lifecycle_authority_boundary',
                risk: 'local transactional',
                actors: [
                    'applicant' => 'citizen_applicant',
                    'operator' => 'primary_operator',
                    'recipient' => 'sample_recipient',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'application_type' => 'new',
                    'citizen_submitted' => true,
                    'municipality_received' => true,
                    'official_application_number' => null,
                    'payment_schedule_status' => 'paid',
                    'collection_status' => 'receipted',
                    'receipt_status' => 'issued',
                    'clearances_completed' => true,
                    'ready_for_authority_review' => true,
                    'can_release' => false,
                    'permit_release_status' => 'blocked',
                    'public_verification_status' => 'artifact_only',
                    'external_calls' => 0,
                    'irreversible_actions' => false,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'citizen_permit_submission_visibility' => new LifecycleScenarioDefinition(
                key: 'citizen_permit_submission_visibility',
                label: 'Citizen permit formal submission visibility',
                mode: 'citizen_permit_submission_visibility',
                risk: 'local transactional',
                actors: [
                    'applicant' => 'citizen_applicant',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'prepared_state' => 'draft',
                    'browser_performs_submission' => true,
                    'canonical_state' => 'assessment',
                    'official_application_number' => null,
                    'assessment_count' => 0,
                    'payment_schedule_count' => 0,
                    'external_calls' => 0,
                    'irreversible_actions' => false,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'citizen_permit_authority_review_visibility' => new LifecycleScenarioDefinition(
                key: 'citizen_permit_authority_review_visibility',
                label: 'Citizen permit authority review visibility',
                mode: 'citizen_permit_authority_review_visibility',
                risk: 'local transactional',
                actors: [
                    'applicant' => 'citizen_applicant',
                    'operator' => 'primary_operator',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'canonical_state' => 'pending_payment',
                    'payment_schedule_status' => 'paid',
                    'collection_status' => 'receipted',
                    'receipt_status' => 'issued',
                    'clearances_completed' => true,
                    'ready_for_authority_review' => true,
                    'can_release' => false,
                    'permit_artifact_status' => 'generated_artifact_available',
                    'public_verification_status' => 'artifact_only',
                    'citizen_payment_detail' => 'read_only',
                    'can_reconcile_online' => false,
                    'online_payment_status' => 'blocked',
                    'browser_is_read_only' => true,
                    'external_calls' => 0,
                    'irreversible_actions' => false,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'citizen_permit_processing_visibility' => new LifecycleScenarioDefinition(
                key: 'citizen_permit_processing_visibility',
                label: 'Citizen permit processing visibility',
                mode: 'citizen_permit_processing_visibility',
                risk: 'local transactional',
                actors: [
                    'applicant' => 'citizen_applicant',
                    'operator' => 'primary_operator',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'canonical_state' => 'pending_payment',
                    'assessment_status' => 'computed',
                    'payment_schedule_status' => 'pending',
                    'online_payment_status' => 'blocked',
                    'can_pay_online' => false,
                    'browser_is_read_only' => true,
                    'citizen_timeline_visible' => true,
                    'external_calls' => 0,
                    'irreversible_actions' => false,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'citizen_existing_business_registry_safety' => new LifecycleScenarioDefinition(
                key: 'citizen_existing_business_registry_safety',
                label: 'Citizen existing-business reuse and registry safety',
                mode: 'citizen_existing_business_registry_safety',
                risk: 'local transactional',
                actors: [
                    'applicant' => 'citizen_applicant',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'canonical_state' => 'draft',
                    'existing_business_reused' => true,
                    'cross_owner_business_rejected' => true,
                    'registry_facts_read_only' => true,
                    'official_application_number' => null,
                    'assessment_count' => 0,
                    'external_calls' => 0,
                    'irreversible_actions' => false,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'citizen_permit_draft_document_visibility' => new LifecycleScenarioDefinition(
                key: 'citizen_permit_draft_document_visibility',
                label: 'Citizen permit draft document visibility',
                mode: 'citizen_permit_draft_document_visibility',
                risk: 'local transactional',
                actors: [
                    'applicant' => 'citizen_applicant',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'canonical_state' => 'draft',
                    'browser_performs_document_upload' => true,
                    'document_count' => 1,
                    'submission_readiness' => 'not_determined',
                    'official_application_number' => null,
                    'assessment_count' => 0,
                    'external_calls' => 0,
                    'irreversible_actions' => false,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'citizen_permit_draft_edit_visibility' => new LifecycleScenarioDefinition(
                key: 'citizen_permit_draft_edit_visibility',
                label: 'Citizen permit draft edit visibility',
                mode: 'citizen_permit_draft_edit_visibility',
                risk: 'local transactional',
                actors: [
                    'applicant' => 'citizen_applicant',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'canonical_state' => 'draft',
                    'browser_performs_edit' => true,
                    'official_application_number' => null,
                    'assessment_count' => 0,
                    'activity_count' => 2,
                    'external_calls' => 0,
                    'irreversible_actions' => false,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'citizen_permit_draft_visibility' => new LifecycleScenarioDefinition(
                key: 'citizen_permit_draft_visibility',
                label: 'Citizen permit draft visibility',
                mode: 'citizen_permit_draft_visibility',
                risk: 'local transactional',
                actors: [
                    'applicant' => 'citizen_applicant',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'application_type' => 'new',
                    'canonical_state' => 'draft',
                    'official_application_number' => null,
                    'assessment_count' => 0,
                    'activity_count' => 2,
                    'external_calls' => 0,
                    'irreversible_actions' => false,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'new_permit_lifecycle_authority_boundary' => new LifecycleScenarioDefinition(
                key: 'new_permit_lifecycle_authority_boundary',
                label: 'New permit lifecycle to authority boundary',
                mode: 'new_permit_lifecycle_authority_boundary',
                risk: 'local transactional',
                actors: [
                    'operator' => 'primary_operator',
                    'recipient' => 'sample_recipient',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'application_type' => 'new',
                    'payment_schedule_status' => 'paid',
                    'collection_status' => 'receipted',
                    'receipt_status' => 'issued',
                    'clearances_completed' => true,
                    'ready_for_authority_review' => true,
                    'can_release' => false,
                    'permit_release_status' => 'blocked',
                    'public_verification_status' => 'artifact_only',
                    'external_calls' => 0,
                    'irreversible_actions' => false,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'manual_collection_receipt_visibility' => new LifecycleScenarioDefinition(
                key: 'manual_collection_receipt_visibility',
                label: 'Manual collection receipt visibility',
                mode: 'manual_collection_receipt_visibility',
                risk: 'local transactional',
                actors: [
                    'operator' => 'primary_operator',
                    'recipient' => 'sample_recipient',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'payment_schedule_status' => 'paid',
                    'collection_status' => 'receipted',
                    'receipt_status' => 'issued',
                    'receipt_void_status' => 'blocked',
                    'numbering_authority' => 'manual',
                    'external_calls' => 0,
                    'irreversible_actions' => false,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'permit_application_pending_payment_visibility' => new LifecycleScenarioDefinition(
                key: 'permit_application_pending_payment_visibility',
                label: 'Permit application pending payment visibility',
                mode: 'permit_application_pending_payment_visibility',
                risk: 'local transactional',
                actors: [
                    'operator' => 'primary_operator',
                    'recipient' => 'sample_recipient',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'canonical_state' => 'pending_payment',
                    'display_status' => 'pending_payment',
                    'payment_schedule_status' => 'pending',
                    'external_calls' => 0,
                    'can_continue' => true,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'renewal_permit_lifecycle_foundation' => new LifecycleScenarioDefinition(
                key: 'renewal_permit_lifecycle_foundation',
                label: 'Renewal permit lifecycle foundation',
                mode: 'renewal_permit_lifecycle_foundation',
                risk: 'local transactional',
                actors: [
                    'operator' => 'primary_operator',
                    'recipient' => 'sample_recipient',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'application_type' => 'renewal',
                    'canonical_state' => 'pending_payment',
                    'display_status' => 'pending_payment',
                    'payment_schedule_status' => 'pending',
                    'renewal_policy_status' => 'policy_boundary',
                    'external_calls' => 0,
                    'can_continue' => true,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'amendment_permit_lifecycle_foundation' => new LifecycleScenarioDefinition(
                key: 'amendment_permit_lifecycle_foundation',
                label: 'Amendment permit lifecycle foundation',
                mode: 'amendment_permit_lifecycle_foundation',
                risk: 'local transactional',
                actors: [
                    'operator' => 'primary_operator',
                    'recipient' => 'sample_recipient',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'application_type' => 'amendment',
                    'canonical_state' => 'pending_payment',
                    'display_status' => 'pending_payment',
                    'payment_schedule_status' => 'pending',
                    'amendment_policy_status' => 'policy_boundary',
                    'external_calls' => 0,
                    'can_continue' => true,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'transfer_permit_lifecycle_foundation' => new LifecycleScenarioDefinition(
                key: 'transfer_permit_lifecycle_foundation',
                label: 'Transfer permit lifecycle foundation',
                mode: 'transfer_permit_lifecycle_foundation',
                risk: 'local transactional',
                actors: [
                    'operator' => 'primary_operator',
                    'recipient' => 'sample_recipient',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'application_type' => 'transfer',
                    'canonical_state' => 'pending_payment',
                    'display_status' => 'pending_payment',
                    'payment_schedule_status' => 'pending',
                    'transfer_policy_status' => 'policy_boundary',
                    'external_calls' => 0,
                    'can_continue' => true,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'retirement_permit_lifecycle_foundation' => new LifecycleScenarioDefinition(
                key: 'retirement_permit_lifecycle_foundation',
                label: 'Retirement permit lifecycle foundation',
                mode: 'retirement_permit_lifecycle_foundation',
                risk: 'local transactional',
                actors: [
                    'operator' => 'primary_operator',
                    'recipient' => 'sample_recipient',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'application_type' => 'retirement',
                    'canonical_state' => 'pending_payment',
                    'display_status' => 'pending_payment',
                    'payment_schedule_status' => 'pending',
                    'retirement_policy_status' => 'policy_boundary',
                    'external_calls' => 0,
                    'can_continue' => true,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'permit_application_cancelled_visibility' => new LifecycleScenarioDefinition(
                key: 'permit_application_cancelled_visibility',
                label: 'Permit application cancelled visibility',
                mode: 'permit_application_cancelled_visibility',
                risk: 'local transactional',
                actors: [
                    'operator' => 'primary_operator',
                    'recipient' => 'sample_recipient',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'canonical_state' => 'cancelled',
                    'display_status' => 'cancelled',
                    'is_terminal' => true,
                    'can_continue' => false,
                    'external_calls' => 0,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'revenue_code_fee_catalog_visibility' => new LifecycleScenarioDefinition(
                key: 'revenue_code_fee_catalog_visibility',
                label: 'Revenue Code fee catalog visibility',
                mode: 'revenue_code_fee_catalog_visibility',
                risk: 'local transactional',
                actors: [
                    'operator' => 'primary_operator',
                    'recipient' => 'sample_recipient',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'fee_rule_code' => 'MRC-2A-02-B-RETAIL-BUSINESS-TAX',
                    'range_count' => 23,
                    'catalog_status' => 'recorded_non_executable',
                    'policy_boundary' => 'new_business_initial_local_business_tax_exemption',
                    'provision_count' => 77,
                    'reconciliation_required_count' => 76,
                    'provision_code' => 'MRC-2A-02-B-WHOLESALERS',
                    'schedule_row_count' => 24,
                    'schedule_overlap_count' => 1,
                    'schedule_gap_count' => 0,
                    'schedule_execution_ready' => false,
                    'schedule_count' => 4,
                    'schedule_total_row_count' => 82,
                    'schedule_total_overlap_count' => 3,
                    'schedule_total_reconciliation_required_count' => 7,
                    'external_calls' => 0,
                    'irreversible_actions' => false,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'revenue_code_executability_safety' => new LifecycleScenarioDefinition(
                key: 'revenue_code_executability_safety',
                label: 'Revenue Code executability and reconciliation safety',
                mode: 'revenue_code_executability_safety',
                risk: 'local transactional',
                actors: [
                    'operator' => 'primary_operator',
                    'recipient' => 'sample_recipient',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'exact_fee_rule_code' => 'MRC-3A-04-BUSINESS-INSPECTION',
                    'exact_amount_cents' => 35_000,
                    'exact_execution_status' => 'executable',
                    'blocked_fee_rule_code' => 'MRC-3A-02-NEW-MAYORS-PERMIT-MICRO',
                    'blocked_execution_status' => 'blocked',
                    'blocked_assessment_count' => 0,
                    'external_calls' => 0,
                    'irreversible_actions' => false,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'assessment_policy_boundary_visibility' => new LifecycleScenarioDefinition(
                key: 'assessment_policy_boundary_visibility',
                label: 'Assessment policy boundary visibility',
                mode: 'assessment_policy_boundary_visibility',
                risk: 'local transactional',
                actors: [
                    'operator' => 'primary_operator',
                    'recipient' => 'sample_recipient',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'assessment_policy_status' => 'blocked',
                    'assessment_count' => 0,
                    'external_calls' => 0,
                    'irreversible_actions' => false,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
            'storyboard_terminal_state_visibility' => new LifecycleScenarioDefinition(
                key: 'storyboard_terminal_state_visibility',
                label: 'Storyboard terminal export visibility',
                mode: 'storyboard_terminal_state_visibility',
                risk: 'local transactional',
                actors: [
                    'operator' => 'primary_operator',
                    'recipient' => 'sample_recipient',
                ],
                safety: [
                    'environments' => ['local', 'testing'],
                    'external_integrations' => false,
                    'irreversible_actions' => false,
                    'notifications' => false,
                ],
                expectations: [
                    'canonical_state' => 'exported',
                    'display_status' => 'completed',
                    'pdf_exports' => 1,
                    'video_exports' => 1,
                    'external_calls' => 0,
                    'can_continue' => true,
                ],
                viewports: [
                    'desktop' => [
                        'width' => 1440,
                        'height' => 900,
                    ],
                    'mobile' => [
                        'width' => 390,
                        'height' => 844,
                    ],
                ],
            ),
        ];
    }

    public function get(string $key): LifecycleScenarioDefinition
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException("Unknown lifecycle scenario [{$key}].");
    }
}
