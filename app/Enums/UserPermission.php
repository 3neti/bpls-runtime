<?php

namespace App\Enums;

enum UserPermission: string
{
    case AccessCitizen = 'citizen.access';
    case CreateOwnPermitApplications = 'citizen.permit_applications.create';
    case EditOwnPermitApplications = 'citizen.permit_applications.edit';
    case ViewOwnPermitApplications = 'citizen.permit_applications.view';
    case AccessStaff = 'staff.access';
    case ViewPermitApplications = 'permit_applications.view';
    case CreatePermitApplications = 'permit_applications.create';
    case AssessPermitApplications = 'permit_applications.assess';
    case UpdatePermitApplicationStatus = 'permit_applications.status_update';
    case CompletePermitClearances = 'permit_clearances.complete';
    case ViewFeeRules = 'fee_rules.view';
    case ViewPaymentSchedules = 'payment_schedules.view';
    case PreparePaymentSchedules = 'payment_schedules.prepare';
    case ViewCollections = 'collections.view';
    case RecordCollections = 'collections.record';
    case ViewReceipts = 'receipts.view';
    case IssueReceipts = 'receipts.issue';
    case VoidReceipts = 'receipts.void';
    case ViewReports = 'reports.view';
    case ManageStoryboards = 'storyboards.manage';
}
