<?php

namespace App\Enums;

enum StakeholderPreviewPersona: string
{
    case Citizen = 'citizen';
    case Bplo = 'bplo';
    case Treasury = 'treasury';
    case Management = 'management';

    public function label(): string
    {
        return match ($this) {
            self::Citizen => 'Citizen',
            self::Bplo => 'BPLO',
            self::Treasury => 'Treasury',
            self::Management => 'Management',
        };
    }

    public function accountName(): string
    {
        return match ($this) {
            self::Citizen => 'Preview Citizen',
            self::Bplo => 'Preview BPLO Operator',
            self::Treasury => 'Preview Treasury Operator',
            self::Management => 'Preview Municipal Management',
        };
    }

    public function approvedEmail(): string
    {
        return match ($this) {
            self::Citizen => 'stakeholder.preview.citizen@example.test',
            self::Bplo => 'stakeholder.preview.bplo@example.test',
            self::Treasury => 'stakeholder.preview.treasury@example.test',
            self::Management => 'stakeholder.preview.management@example.test',
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
            self::Management => 'Inspect reports, users, roles, municipality settings, fees, and visible policy boundaries.',
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
        };
    }
}
