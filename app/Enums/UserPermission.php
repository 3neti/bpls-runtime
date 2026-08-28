<?php

namespace App\Enums;

enum UserPermission: string
{
    case AccessCitizen = 'citizen.access';
    case CreateOwnPermitApplications = 'citizen.permit_applications.create';
    case EditOwnPermitApplications = 'citizen.permit_applications.edit';
    case SubmitOwnPermitApplications = 'citizen.permit_applications.submit';
    case UploadOwnPermitApplicationDocuments = 'citizen.permit_application_documents.create';
    case ViewOwnPermitApplications = 'citizen.permit_applications.view';
    case ViewOwnBusinessPermitEvaluations = 'citizen.business_permit_evaluations.view';
    case CorrectOwnEvaluationDeclarations = 'citizen.business_permit_evaluations.correct_declarations';
    case ViewOwnPermitApplicationDocuments = 'citizen.permit_application_documents.view';
    case ViewOwnPermitApplicationFinancials = 'citizen.permit_application_financials.view';
    case AccessStaff = 'staff.access';
    case ViewPermitApplications = 'permit_applications.view';
    case ViewBusinessPermitEvaluations = 'business_permit_evaluations.view';
    case ContributeBusinessPermitEvaluations = 'business_permit_evaluations.contribute';
    case CounterCheckBusinessPermitEvaluations = 'business_permit_evaluations.counter_check';
    case CorrectEvaluationLinesOfBusiness = 'business_permit_evaluations.correct_lines_of_business';
    case CreatePermitApplications = 'permit_applications.create';
    case AssessPermitApplications = 'permit_applications.assess';
    case ApproveAssessments = 'assessments.approve';
    case UpdatePermitApplicationStatus = 'permit_applications.status_update';
    case CompletePermitClearances = 'permit_clearances.complete';
    case ViewFeeRules = 'fee_rules.view';
    case ViewBillingGroups = 'billing_groups.view';
    case ManageBillingGroups = 'billing_groups.manage';
    case ViewBillingGroupRecords = 'billing_group_records.view';
    case CreateBillingGroupRecords = 'billing_group_records.create';
    case RecordBillingGroupReconciliationEvidence = 'billing_group_reconciliations.create';
    case ViewPaymentSchedules = 'payment_schedules.view';
    case PreparePaymentSchedules = 'payment_schedules.prepare';
    case ViewCollections = 'collections.view';
    case RecordCollections = 'collections.record';
    case ViewReceipts = 'receipts.view';
    case IssueReceipts = 'receipts.issue';
    case VoidReceipts = 'receipts.void';
    case ViewReports = 'reports.view';
    case ViewUsers = 'users.view';
    case ViewRoles = 'roles.view';
    case ViewMunicipalityConfiguration = 'municipality_configuration.view';
    case ManageStoryboards = 'storyboards.manage';
}
