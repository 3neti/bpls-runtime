<?php

namespace App\Actions;

use App\Models\PermitApplication;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class ProjectSyntheticPermitCalendar
{
    /**
     * @return array{document_issued_on: string, valid_until: string, audit_issued_at: string, method: string, production_authority: false}
     */
    public function handle(PermitApplication $permitApplication, CarbonInterface $auditIssuedAt): array
    {
        $firstOfMonth = CarbonImmutable::create(
            $permitApplication->application_year,
            $auditIssuedAt->month,
            1,
            0,
            0,
            0,
            $auditIssuedAt->timezone,
        );
        $documentIssuedOn = $firstOfMonth->day(min($auditIssuedAt->day, $firstOfMonth->daysInMonth));

        return [
            'document_issued_on' => $documentIssuedOn->toDateString(),
            'valid_until' => $documentIssuedOn->endOfYear()->toDateString(),
            'audit_issued_at' => $auditIssuedAt->toIso8601String(),
            'method' => 'wall_clock_month_day_projected_to_synthetic_permit_year_v1',
            'production_authority' => false,
        ];
    }
}
