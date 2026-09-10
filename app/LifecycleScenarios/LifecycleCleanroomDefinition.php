<?php

namespace App\LifecycleScenarios;

use App\Enums\UserPermission;

class LifecycleCleanroomDefinition
{
    public const string Revision = 'complete_business_permit_lifecycle_v5';

    /** @return array<string, array{label: string, permissions: list<UserPermission>}> */
    public function actors(): array
    {
        $mayorName = (string) config('municipality.officials.municipal_mayor.name', 'Ramses Troy D. Olegario');

        return [
            'citizen' => ['label' => 'Citizen', 'permissions' => [UserPermission::AccessCitizen, UserPermission::CreateOwnPermitApplications, UserPermission::EditOwnPermitApplications, UserPermission::SubmitOwnPermitApplications, UserPermission::UploadOwnPermitApplicationDocuments, UserPermission::ViewOwnPermitApplications, UserPermission::ViewOwnPermitApplicationDocuments, UserPermission::ViewOwnPermitApplicationFinancials, UserPermission::ViewOwnBusinessPermitEvaluations]],
            'intake' => ['label' => 'BPLO Intake', 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::CreatePermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::DetermineBploRouting]],
            'assessment_officer' => ['label' => 'Assessment Officer', 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::AssessPermitApplications, UserPermission::ViewPaymentSchedules, UserPermission::PreparePaymentSchedules]],
            'assessor' => ['label' => 'Municipal Assessor', 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::ContributeBusinessPermitEvaluations]],
            'engineering' => ['label' => 'Engineering', 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::ContributeBusinessPermitEvaluations]],
            'health' => ['label' => 'Health', 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::ContributeBusinessPermitEvaluations]],
            'menro' => ['label' => 'MENRO', 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::ContributeBusinessPermitEvaluations]],
            'treasury' => ['label' => 'Treasury', 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::CounterCheckBusinessPermitEvaluations, UserPermission::CorrectEvaluationLinesOfBusiness]],
            'municipal_treasurer' => ['label' => 'Municipal Treasurer', 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::ApproveAssessments]],
            'cashier' => ['label' => 'Cashier', 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewPaymentSchedules, UserPermission::RecordCollections, UserPermission::ViewReceipts, UserPermission::IssueReceipts]],
            'permit_issuer' => ['label' => 'Mayor '.$mayorName, 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications]],
            'releasing_officer' => ['label' => 'BPLO Releasing Officer', 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications]],
        ];
    }

    /** @return list<array{key: string, year: int, label: string, description: string, mode: string, actor: string|null, milestone: string}> */
    public function steps(): array
    {
        return [
            $this->step('cleanroom_started', 2025, 'Cleanroom started', 'A uniquely owned synthetic actor set is ready; no municipal transaction exists.', 'complete_on_start', null, 'Cleanroom ready'),
            $this->step('citizen_intake', 2025, 'Application Form completed and lodged', 'One applicant action saves the officially unnumbered Application, freezes Page 1, and lodges it for municipal processing through the canonical draft and submission actions.', 'product_form', 'citizen', 'Lodge Application'),
            $this->step('bplo_routing', 2025, 'BPLO routing determined', 'BPLO records the selected concerned offices, situational reasons, LOB/application context, and required work after lodging.', 'product_form', 'intake', 'BPLO routing'),
            $this->step('evaluation_initialized', 2025, 'Office evaluation work created', 'Required office reviews are assigned from the submitted application and BPLO routing. No amount becomes payable and no Assessment is created at this step.', 'system_action', 'assessment_officer', 'Office reviews assigned'),
            $this->step('assessor_responsibilities', 2025, 'Assessor inputs completed', 'The Assessor confirms Retail and Food Service amounts and issues Paperless Payment Orders.', 'product_form', 'assessor', 'Departmental inputs'),
            $this->step('engineering_responsibility', 2025, 'Engineering input completed', 'Engineering confirms the Retail premises review and provisional Mayor\'s Permit Fee responsibility.', 'product_form', 'engineering', 'Departmental inputs'),
            $this->step('health_responsibilities', 2025, 'Health inputs completed', 'Health confirms the Health Certificate and Sanitary Permit responsibilities.', 'product_form', 'health', 'Departmental inputs'),
            $this->step('menro_responsibility', 2025, 'MENRO input completed', 'MENRO confirms the Solid Waste Management responsibility.', 'product_form', 'menro', 'Departmental inputs'),
            $this->step('assessment_prepared', 2025, 'Computation/Assessment Slip opened', 'The Assessment Officer consolidates eligible Paperless Payment Orders and governed pricing exactly once into the immutable Assessment and its separate slip.', 'product_form', 'assessment_officer', 'Assessment'),
            $this->step('treasury_counter_check', 2025, 'Treasury counter-check complete', 'Treasury records no correction against the exact Assessment and source Evaluation version.', 'product_form', 'treasury', 'Treasury'),
            $this->step('treasurer_approved', 2025, 'Municipal Treasurer exact approval', 'The Municipal Treasurer approves the immutable Assessment fingerprint.', 'product_form', 'municipal_treasurer', 'Approval'),
            $this->step('payable_created', 2025, 'Payable created', 'The approved Assessment becomes one pending Payment Schedule.', 'product_form', 'assessment_officer', '2025 approved payable'),
            $this->step('qr_payment_requested', 2025, 'QR Ph request generated', 'The Citizen generates the current QR Ph request for the exact payable amount. No Collection exists yet.', 'product_form', 'citizen', 'Generate QR Ph'),
            $this->step('qr_payment_collected', 2025, 'Synthetic payment confirmed', 'The Cashier receives the current Citizen-generated QR Ph handoff and records one canonical synthetic Collection; no real funds move.', 'product_form', 'cashier', 'Confirm synthetic payment'),
            $this->step('official_receipt_issued', 2025, 'AF No. 51 issued', 'The Cashier issues the AF No. 51 Official Receipt from canonical Collection truth.', 'product_form', 'cashier', 'AF No. 51 issued'),
            $this->step('post_payment_certifications_commissioned', 2025, 'Post-payment certifications commissioned', 'The actual BPLO routing determines the offices asked to review the bound Official Receipt and certify synthetic cleanroom results.', 'system_action', 'intake', 'Post-payment office work'),
            $this->step('assessor_post_payment_certified', 2025, 'Assessor post-payment certification', 'The routed Assessor reviews the bound Official Receipt and records synthetic-only certification evidence.', 'product_form', 'assessor', 'Post-payment certification'),
            $this->step('engineering_post_payment_certified', 2025, 'Engineering post-payment certification', 'The routed Engineering office reviews the bound Official Receipt and records synthetic-only certification evidence.', 'product_form', 'engineering', 'Post-payment certification'),
            $this->step('health_post_payment_certified', 2025, 'Health post-payment certification', 'The routed Health office reviews the bound Official Receipt and records synthetic-only certification evidence.', 'product_form', 'health', 'Post-payment certification'),
            $this->step('menro_post_payment_certified', 2025, 'MENRO post-payment certification', 'The routed MENRO office reviews the bound Official Receipt and records synthetic-only certification evidence.', 'product_form', 'menro', 'Post-payment certification'),
            $this->step('permit_ready', 2025, 'Ready for Mayoral Authorization', 'The deterministic PermitReadiness projection passes only after OR binding and every routing-derived post-payment certification.', 'product_form', 'permit_issuer', 'Mayoral Authorization ready'),
            $this->step('permit_issued', 2025, 'Mayoral Authorization recorded and Permit issued', 'The cleanroom records bounded synthetic Mayoral Authorization evidence for the configured Municipal Mayor and issues a synthetic-only BP-YYYY-XXXX specimen. It does not represent the Mayor\'s login, signature, or production approval.', 'product_form', 'permit_issuer', 'Record Mayoral Authorization'),
            $this->step('permit_released', 2025, 'Permit released', 'The BPLO Releasing Officer records release of the already-issued synthetic specimen.', 'product_form', 'releasing_officer', 'Permit released'),
            $this->step('public_verification', 2025, 'Public verification available', 'The public QR/reference resolves to the exact released synthetic Permit identity and safe public fields only.', 'product_form', 'citizen', 'Citizen permit ready'),
            $this->step('renewal_lodged', 2026, '2026 Renewal lodged', 'Canonical Renewal intake reuses the exact Municipal Owner and Business without mutating registry identity.', 'system_action', 'intake', 'Renewal lodged'),
            $this->step('renewal_bplo_routing', 2026, 'Renewal BPLO routing determined', 'BPLO makes a fresh situational routing determination for the lodged Renewal.', 'product_form', 'intake', 'Renewal BPLO routing'),
            $this->step('renewal_evaluation_initialized', 2026, 'Renewal office evaluation work created', 'Required Renewal office reviews are assigned from the submitted application and BPLO routing. No amount becomes payable and no Assessment is created at this step.', 'system_action', 'assessment_officer', 'Renewal office reviews assigned'),
            $this->step('renewal_assessor_responsibilities', 2026, 'Renewal Assessor inputs completed', 'The Assessor confirms both Renewal Business Tax responsibilities.', 'product_form', 'assessor', 'Renewal departmental inputs'),
            $this->step('renewal_engineering_responsibility', 2026, 'Renewal Engineering input completed', 'Engineering confirms the Renewal premises responsibility.', 'product_form', 'engineering', 'Renewal departmental inputs'),
            $this->step('renewal_health_responsibilities', 2026, 'Renewal Health inputs completed', 'Health confirms both Renewal health responsibilities.', 'product_form', 'health', 'Renewal departmental inputs'),
            $this->step('renewal_menro_responsibility', 2026, 'Renewal MENRO input completed', 'MENRO confirms the Renewal waste-management responsibility.', 'product_form', 'menro', 'Renewal departmental inputs'),
            $this->step('renewal_assessment_prepared', 2026, 'Renewal Computation/Assessment Slip opened', 'The Assessment Officer consolidates the Renewal office contributions into its immutable Assessment and separate slip.', 'product_form', 'assessment_officer', 'Renewal Assessment'),
            $this->step('renewal_treasury_counter_check', 2026, 'Renewal Treasury counter-check complete', 'Treasury counter-checks the exact Renewal Assessment.', 'product_form', 'treasury', 'Renewal Treasury'),
            $this->step('renewal_treasurer_approved', 2026, 'Renewal Municipal Treasurer approval', 'The Municipal Treasurer approves the immutable Renewal Assessment.', 'product_form', 'municipal_treasurer', 'Renewal approval'),
            $this->step('renewal_payable_created', 2026, 'Renewal Payable created', 'The approved Renewal Assessment becomes one pending Payment Schedule.', 'product_form', 'assessment_officer', 'Two-year chronology complete'),
        ];
    }

    /** @return array{key: string, year: int, label: string, description: string, mode: string, actor: string|null, milestone: string} */
    private function step(string $key, int $year, string $label, string $description, string $mode, ?string $actor, string $milestone): array
    {
        return compact('key', 'year', 'label', 'description', 'mode', 'actor', 'milestone');
    }
}
