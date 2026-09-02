<?php

namespace App\Enums;

enum StakeholderPreviewPersona: string
{
    case Citizen = 'citizen';
    case Bplo = 'bplo';
    case AssessmentOfficer = 'assessment_officer';
    case Treasury = 'treasury';
    case MunicipalTreasurer = 'municipal_treasurer';
    case Cashier = 'cashier';
    case Management = 'management';
    case Engineering = 'engineering';
    case Mpdo = 'mpdo';
    case Assessor = 'assessor';
    case Health = 'health';
    case Menro = 'menro';
    case MayorOffice = 'mayor_office';
    case Releasing = 'releasing';

    public function label(): string
    {
        return match ($this) {
            self::Citizen => 'Citizen',
            self::Bplo => 'BPLO',
            self::AssessmentOfficer => 'Assessment Officer',
            self::Treasury => 'Treasury',
            self::MunicipalTreasurer => 'Municipal Treasurer',
            self::Cashier => 'Cashier',
            self::Management => 'Management',
            self::Engineering => 'Engineering',
            self::Mpdo => 'MPDO / MPDC',
            self::Assessor => 'Assessor',
            self::Health => 'Health',
            self::Menro => 'MENRO',
            self::MayorOffice => "Mayor's Office",
            self::Releasing => 'Releasing Officer',
        };
    }

    public function accountName(): string
    {
        return match ($this) {
            self::Citizen => 'Preview Citizen',
            self::Bplo => 'Preview BPLO Operator',
            self::AssessmentOfficer => 'Preview Assessment Officer',
            self::Treasury => 'Preview Treasury Operator',
            self::MunicipalTreasurer => 'Preview Municipal Treasurer',
            self::Cashier => 'Preview Cashier',
            self::Management => 'Preview Municipal Management',
            self::Engineering => 'Preview Engineering Officer',
            self::Mpdo => 'Preview MPDO Officer',
            self::Assessor => 'Preview Municipal Assessor',
            self::Health => 'Preview Health Officer',
            self::Menro => 'Preview MENRO Officer',
            self::MayorOffice => "Preview Mayor's Office",
            self::Releasing => 'Preview BPLO Releasing Officer',
        };
    }

    public function approvedEmail(): string
    {
        return match ($this) {
            self::Citizen => 'stakeholder.preview.citizen@example.test',
            self::Bplo => 'stakeholder.preview.bplo@example.test',
            self::AssessmentOfficer => 'stakeholder.preview.assessment-officer@example.test',
            self::Treasury => 'stakeholder.preview.treasury@example.test',
            self::MunicipalTreasurer => 'stakeholder.preview.municipal-treasurer@example.test',
            self::Cashier => 'stakeholder.preview.cashier@example.test',
            self::Management => 'stakeholder.preview.management@example.test',
            self::Engineering => 'stakeholder.preview.engineering@example.test',
            self::Mpdo => 'stakeholder.preview.mpdo@example.test',
            self::Assessor => 'stakeholder.preview.assessor@example.test',
            self::Health => 'stakeholder.preview.health@example.test',
            self::Menro => 'stakeholder.preview.menro@example.test',
            self::MayorOffice => 'stakeholder.preview.mayor-office@example.test',
            self::Releasing => 'stakeholder.preview.releasing@example.test',
        };
    }

    public function roleCode(): string
    {
        return 'preview_'.$this->value;
    }

    public function description(): string
    {
        return match ($this) {
            self::Citizen => 'Apply, submit, track processing, and inspect payment and clearance progress.',
            self::Bplo => 'Receive applications, coordinate clearances, and inspect fee rules without preparing assessments or collecting payment.',
            self::AssessmentOfficer => 'Consolidate the exact assessment and prepare its payment schedule for Treasurer approval.',
            self::Treasury => 'Counter-check prepared Assessments against their source Evaluations and inspect payment schedules, collections, receipts, and Treasury reports.',
            self::MunicipalTreasurer => 'Approve or return one exact immutable Assessment snapshot without performing the earlier Treasury counter-check.',
            self::Cashier => 'Record authorized payments and issue official receipts without approving assessments.',
            self::Management => 'Review reports, user access, municipality settings, and fees.',
            self::Engineering => 'Review applications routed to Engineering and submit the office charge for consolidation.',
            self::Mpdo => 'Review applications routed to MPDO and submit the office charge for consolidation.',
            self::Assessor => 'Review applications routed to the Assessor and submit the office charge for consolidation.',
            self::Health => 'Review applications routed to Health and submit the office charge for consolidation.',
            self::Menro => 'Review applications routed to MENRO and submit the office charge for consolidation.',
            self::MayorOffice => 'Review paid, cleared applications and decide whether the sample permit may proceed.',
            self::Releasing => 'Complete the sample permit preview after approval.',
        };
    }

    /** @return list<UserPermission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Citizen => [
                UserPermission::AccessCitizen,
                UserPermission::CreateOwnPermitApplications,
                UserPermission::EditOwnPermitApplications,
                UserPermission::SubmitOwnPermitApplications,
                UserPermission::UploadOwnPermitApplicationDocuments,
                UserPermission::ViewOwnPermitApplications,
                UserPermission::ViewOwnPermitApplicationDocuments,
                UserPermission::ViewOwnPermitApplicationFinancials,
                UserPermission::ViewOwnBusinessPermitEvaluations,
                UserPermission::CorrectOwnEvaluationDeclarations,
            ],
            self::Bplo => [
                UserPermission::AccessStaff,
                UserPermission::ViewPermitApplications,
                UserPermission::CreatePermitApplications,
                UserPermission::UpdatePermitApplicationStatus,
                UserPermission::CompletePermitClearances,
                UserPermission::ViewFeeRules,
                UserPermission::ViewPaymentSchedules,
                UserPermission::ViewBusinessPermitEvaluations,
                UserPermission::DetermineBploRouting,
            ],
            self::AssessmentOfficer => [
                UserPermission::AccessStaff,
                UserPermission::ViewPermitApplications,
                UserPermission::AssessPermitApplications,
                UserPermission::ViewPaymentSchedules,
                UserPermission::PreparePaymentSchedules,
                UserPermission::ViewBusinessPermitEvaluations,
            ],
            self::Treasury => [
                UserPermission::AccessStaff,
                UserPermission::ViewPermitApplications,
                UserPermission::ViewPaymentSchedules,
                UserPermission::ViewCollections,
                UserPermission::ViewReceipts,
                UserPermission::ViewReports,
                UserPermission::ViewBusinessPermitEvaluations,
                UserPermission::CounterCheckBusinessPermitEvaluations,
                UserPermission::CorrectEvaluationLinesOfBusiness,
            ],
            self::MunicipalTreasurer => [
                UserPermission::AccessStaff,
                UserPermission::ViewPermitApplications,
                UserPermission::ViewBusinessPermitEvaluations,
                UserPermission::ApproveAssessments,
                UserPermission::ViewPaymentSchedules,
            ],
            self::Cashier => [
                UserPermission::AccessStaff,
                UserPermission::ViewPaymentSchedules,
                UserPermission::ViewCollections,
                UserPermission::RecordCollections,
                UserPermission::ViewReceipts,
                UserPermission::IssueReceipts,
            ],
            self::Management => [
                UserPermission::AccessStaff,
                UserPermission::ViewPermitApplications,
                UserPermission::ViewPaymentSchedules,
                UserPermission::ViewCollections,
                UserPermission::ViewReceipts,
                UserPermission::ViewReports,
                UserPermission::ViewUsers,
                UserPermission::ViewRoles,
                UserPermission::ViewMunicipalityConfiguration,
                UserPermission::ViewFeeRules,
                UserPermission::ViewBillingGroups,
                UserPermission::ViewBillingGroupRecords,
                UserPermission::ViewBusinessPermitEvaluations,
                UserPermission::ManageStoryboards,
            ],
            self::Engineering,
            self::Mpdo,
            self::Assessor,
            self::Health,
            self::Menro => [
                UserPermission::AccessStaff,
                UserPermission::ViewPermitApplications,
                UserPermission::ViewBusinessPermitEvaluations,
                UserPermission::ContributeBusinessPermitEvaluations,
            ],
            self::MayorOffice,
            self::Releasing => [
                UserPermission::AccessStaff,
                UserPermission::ViewPermitApplications,
            ],
        };
    }

    public function officeCode(): ?string
    {
        return match ($this) {
            self::Engineering => 'engineering',
            self::Mpdo => 'mpdo',
            self::Assessor => 'assessor',
            self::Health => 'health',
            self::Menro => 'menro',
            default => null,
        };
    }

    public function isConcernedOffice(): bool
    {
        return $this->officeCode() !== null;
    }
}
