<?php

namespace App\Support;

use App\Enums\PermitApplicationStatus;
use App\Models\PermitApplication;

final class PermitApplicationLifecyclePresentation
{
    public function status(PermitApplication $application): string
    {
        return match (true) {
            $application->provisionalUatPermitCompletion?->released_at !== null => PermitApplicationStatus::Released->value,
            $application->provisionalUatPermitCompletion?->issued_at !== null => 'issued',
            default => $application->status->value,
        };
    }
}
