<?php

namespace App\Actions;

use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Models\Business;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateRenewalPermitApplicationForExistingBusiness
{
    public function __construct(
        private readonly DescribeRenewalPolicyBoundary $describeRenewalPolicyBoundary,
    ) {}

    /**
     * @param  list<array{
     *     line_of_business_id: int,
     *     declared_gross_sales_cents: int,
     *     capital_investment_cents: int,
     *     quantity: int,
     *     started_on?: string|null
     * }>  $lines
     */
    public function handle(Business $business, int $applicationYear, array $lines, User $submittedBy): PermitApplication
    {
        return DB::transaction(function () use ($business, $applicationYear, $lines, $submittedBy): PermitApplication {
            $permitApplication = PermitApplication::query()->create([
                'business_id' => $business->id,
                'submitted_by_id' => $submittedBy->id,
                'application_number' => null,
                'type' => PermitApplicationType::Renewal,
                'status' => PermitApplicationStatus::Draft,
                'application_year' => $applicationYear,
                'submitted_at' => now(),
                'metadata' => [
                    'renewal_policy_boundary' => $this->describeRenewalPolicyBoundary->handle(PermitApplicationType::Renewal),
                ],
            ]);

            foreach ($lines as $line) {
                $permitApplication->lines()->create($line);
            }

            return $permitApplication->load(['business.owner', 'lines.lineOfBusiness']);
        });
    }
}
