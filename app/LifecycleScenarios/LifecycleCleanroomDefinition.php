<?php

namespace App\LifecycleScenarios;

use App\Enums\UserPermission;

class LifecycleCleanroomDefinition
{
    public const string Revision = 'cleanroom_two_year_chronology_v1';

    /** @return array<string, array{label: string, permissions: list<UserPermission>}> */
    public function actors(): array
    {
        return [
            'citizen' => ['label' => 'Citizen', 'permissions' => [UserPermission::AccessCitizen, UserPermission::CreateOwnPermitApplications, UserPermission::EditOwnPermitApplications, UserPermission::SubmitOwnPermitApplications, UserPermission::UploadOwnPermitApplicationDocuments, UserPermission::ViewOwnPermitApplications, UserPermission::ViewOwnPermitApplicationDocuments, UserPermission::ViewOwnPermitApplicationFinancials, UserPermission::ViewOwnBusinessPermitEvaluations]],
            'intake' => ['label' => 'BPLO Intake', 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::CreatePermitApplications]],
            'assessment_officer' => ['label' => 'Assessment Officer', 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::AssessPermitApplications, UserPermission::ViewPaymentSchedules, UserPermission::PreparePaymentSchedules]],
            'assessor' => ['label' => 'Municipal Assessor', 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::ContributeBusinessPermitEvaluations]],
            'engineering' => ['label' => 'Engineering', 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::ContributeBusinessPermitEvaluations]],
            'health' => ['label' => 'Health', 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::ContributeBusinessPermitEvaluations]],
            'menro' => ['label' => 'MENRO', 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::ContributeBusinessPermitEvaluations]],
            'treasury' => ['label' => 'Treasury', 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::CounterCheckBusinessPermitEvaluations, UserPermission::CorrectEvaluationLinesOfBusiness]],
            'municipal_treasurer' => ['label' => 'Municipal Treasurer', 'permissions' => [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::ApproveAssessments]],
        ];
    }

    /** @return list<array{key: string, year: int, label: string, description: string, mode: string, actor: string|null, milestone: string}> */
    public function steps(): array
    {
        return [
            $this->step('cleanroom_started', 2025, 'Cleanroom started', 'A uniquely owned synthetic actor set is ready; no municipal transaction exists.', 'complete_on_start', null, 'Cleanroom ready'),
            $this->step('citizen_intake', 2025, 'Owner, Business, and draft recorded', 'The real Citizen intake form establishes the Municipal Owner and Business and saves an officially unnumbered draft.', 'product_form', 'citizen', 'Citizen intake'),
            $this->step('application_submitted', 2025, '2025 New Business Permit lodged', 'The Citizen submits the draft through the normal product action and the Municipality receives it for processing.', 'product_form', 'citizen', 'Application lodged'),
            $this->step('evaluation_initialized', 2025, 'Routing and responsibilities created', 'The canonical Evaluation action creates six provisional UAT responsibilities from the declared activities.', 'system_action', 'assessment_officer', 'Responsibilities ready'),
            $this->step('assessor_responsibilities', 2025, 'Assessor inputs completed', 'The Assessor confirms Retail and Food Service Business Tax responsibilities.', 'product_form', 'assessor', 'Departmental inputs'),
            $this->step('engineering_responsibility', 2025, 'Engineering input completed', 'Engineering confirms the Retail premises review and provisional Mayor\'s Permit Fee responsibility.', 'product_form', 'engineering', 'Departmental inputs'),
            $this->step('health_responsibilities', 2025, 'Health inputs completed', 'Health confirms the Health Certificate and Sanitary Permit responsibilities.', 'product_form', 'health', 'Departmental inputs'),
            $this->step('menro_responsibility', 2025, 'MENRO input completed', 'MENRO confirms the Solid Waste Management responsibility.', 'product_form', 'menro', 'Departmental inputs'),
            $this->step('assessment_prepared', 2025, 'Assessment prepared', 'The Assessment Officer creates the immutable Assessment from the ready Evaluation working paper.', 'product_form', 'assessment_officer', 'Assessment'),
            $this->step('treasury_counter_check', 2025, 'Treasury counter-check complete', 'Treasury records no correction against the exact Assessment and source Evaluation version.', 'product_form', 'treasury', 'Treasury'),
            $this->step('treasurer_approved', 2025, 'Municipal Treasurer exact approval', 'The Municipal Treasurer approves the immutable Assessment fingerprint.', 'product_form', 'municipal_treasurer', 'Approval'),
            $this->step('payable_created', 2025, 'Payable created', 'The approved Assessment becomes one pending Payment Schedule.', 'product_form', 'assessment_officer', '2025 approved payable'),
            $this->step('renewal_lodged', 2026, '2026 Renewal lodged', 'Canonical Renewal intake reuses the exact Municipal Owner and Business without mutating registry identity.', 'system_action', 'intake', 'Renewal lodged'),
            $this->step('renewal_evaluation_initialized', 2026, 'Renewal routing and responsibilities created', 'The Renewal receives the same bounded provisional UAT responsibility set.', 'system_action', 'assessment_officer', 'Renewal responsibilities'),
            $this->step('renewal_assessor_responsibilities', 2026, 'Renewal Assessor inputs completed', 'The Assessor confirms both Renewal Business Tax responsibilities.', 'product_form', 'assessor', 'Renewal departmental inputs'),
            $this->step('renewal_engineering_responsibility', 2026, 'Renewal Engineering input completed', 'Engineering confirms the Renewal premises responsibility.', 'product_form', 'engineering', 'Renewal departmental inputs'),
            $this->step('renewal_health_responsibilities', 2026, 'Renewal Health inputs completed', 'Health confirms both Renewal health responsibilities.', 'product_form', 'health', 'Renewal departmental inputs'),
            $this->step('renewal_menro_responsibility', 2026, 'Renewal MENRO input completed', 'MENRO confirms the Renewal waste-management responsibility.', 'product_form', 'menro', 'Renewal departmental inputs'),
            $this->step('renewal_assessment_prepared', 2026, 'Renewal Assessment prepared', 'The Assessment Officer creates the immutable Renewal Assessment.', 'product_form', 'assessment_officer', 'Renewal Assessment'),
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
