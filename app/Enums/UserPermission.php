<?php

namespace App\Enums;

enum UserPermission: string
{
    case AccessStaff = 'staff.access';
    case ViewPermitApplications = 'permit_applications.view';
    case CreatePermitApplications = 'permit_applications.create';
    case AssessPermitApplications = 'permit_applications.assess';
}
