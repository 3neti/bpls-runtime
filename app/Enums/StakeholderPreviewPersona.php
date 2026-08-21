<?php

namespace App\Enums;

enum StakeholderPreviewPersona: string
{
    case Citizen = 'citizen';
    case Bplo = 'bplo';
    case Treasury = 'treasury';
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
            self::Treasury => 'Treasury',
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
            self::Treasury => 'Preview Treasury Operator',
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
            self::Treasury => 'stakeholder.preview.treasury@example.test',
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
            self::Bplo => 'Review applications, assessments, clearances, and authority readiness.',
            self::Treasury => 'Approve assessment amounts, then inspect payment schedules, collections, receipts, and Treasury reports.',
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
            ],
            self::Bplo => [
                UserPermission::AccessStaff,
                UserPermission::ViewPermitApplications,
                UserPermission::CreatePermitApplications,
                UserPermission::AssessPermitApplications,
                UserPermission::UpdatePermitApplicationStatus,
                UserPermission::CompletePermitClearances,
                UserPermission::ViewFeeRules,
                UserPermission::ViewPaymentSchedules,
                UserPermission::PreparePaymentSchedules,
            ],
            self::Treasury => [
                UserPermission::AccessStaff,
                UserPermission::ViewPermitApplications,
                UserPermission::ApproveAssessments,
                UserPermission::ViewPaymentSchedules,
                UserPermission::ViewCollections,
                UserPermission::RecordCollections,
                UserPermission::ViewReceipts,
                UserPermission::IssueReceipts,
                UserPermission::ViewReports,
            ],
            self::Management => [
                UserPermission::AccessStaff,
                UserPermission::ViewPermitApplications,
                UserPermission::CreatePermitApplications,
                UserPermission::AssessPermitApplications,
                UserPermission::UpdatePermitApplicationStatus,
                UserPermission::CompletePermitClearances,
                UserPermission::ViewPaymentSchedules,
                UserPermission::PreparePaymentSchedules,
                UserPermission::ViewCollections,
                UserPermission::RecordCollections,
                UserPermission::ViewReceipts,
                UserPermission::IssueReceipts,
                UserPermission::VoidReceipts,
                UserPermission::ViewReports,
                UserPermission::ViewUsers,
                UserPermission::ViewRoles,
                UserPermission::ViewMunicipalityConfiguration,
                UserPermission::ViewFeeRules,
                UserPermission::ViewBillingGroups,
                UserPermission::ViewBillingGroupRecords,
                UserPermission::ManageStoryboards,
            ],
            self::Engineering,
            self::Mpdo,
            self::Assessor,
            self::Health,
            self::Menro,
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
