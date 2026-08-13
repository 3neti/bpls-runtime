<?php

namespace App\Enums;

enum UserPermission: string
{
    case AccessStaff = 'staff.access';
    case ViewPermitApplications = 'permit_applications.view';
    case CreatePermitApplications = 'permit_applications.create';
    case AssessPermitApplications = 'permit_applications.assess';
    case ViewPaymentSchedules = 'payment_schedules.view';
    case PreparePaymentSchedules = 'payment_schedules.prepare';
    case ViewCollections = 'collections.view';
    case RecordCollections = 'collections.record';
}
