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
        private readonly BuildPermitApplicationDeclarationDraft $buildDeclarationDraft,
        private readonly FreezePermitApplicationDeclaration $freezeDeclaration,
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
                    'applicant_declaration_draft' => $this->buildDeclarationDraft->handle([
                        'application_year' => $applicationYear,
                        'type' => PermitApplicationType::Renewal->value,
                        'date_of_application' => now()->toDateString(),
                        'owner_name' => $business->owner()->value('name'),
                        'owner_email' => $business->owner()->value('email'),
                        'owner_phone' => $business->owner()->value('phone'),
                        'owner_address' => $business->owner()->value('address'),
                        'business_name' => $business->name,
                        'trade_name' => $business->trade_name,
                        'registration_number' => $business->registration_number,
                        'registered_on' => $business->registered_on?->toDateString(),
                        'business_address' => $business->address,
                        'barangay' => $business->barangay,
                        'ownership_type' => $business->ownership_type,
                        'property_index_number' => $business->property_index_number,
                        'business_area_square_meters' => $business->business_area_square_meters,
                        'male_employee_count' => $business->male_employee_count,
                        'female_employee_count' => $business->female_employee_count,
                        'business_contact_number' => $business->contact_number,
                        'business_email' => $business->email,
                        'applicant_printed_name' => $business->owner()->value('name'),
                        'position_title' => 'Owner',
                        'undertaking_accepted' => true,
                    ]),
                ],
            ]);

            foreach ($lines as $line) {
                $permitApplication->lines()->create($line);
            }

            $this->freezeDeclaration->handle($permitApplication, $submittedBy);

            return $permitApplication->load(['business.owner', 'lines.lineOfBusiness']);
        });
    }
}
