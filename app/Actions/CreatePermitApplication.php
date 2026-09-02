<?php

namespace App\Actions;

use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreatePermitApplication
{
    public function __construct(
        private readonly DescribeRenewalPolicyBoundary $describeRenewalPolicyBoundary,
        private readonly DescribeAmendmentPolicyBoundary $describeAmendmentPolicyBoundary,
        private readonly DescribeTransferPolicyBoundary $describeTransferPolicyBoundary,
        private readonly DescribeRetirementPolicyBoundary $describeRetirementPolicyBoundary,
        private readonly BuildPermitApplicationDeclarationDraft $buildDeclarationDraft,
        private readonly FreezePermitApplicationDeclaration $freezeDeclaration,
    ) {}

    /**
     * @param  array{
     *     owner_name: string,
     *     owner_email?: string|null,
     *     owner_phone?: string|null,
     *     owner_address?: string|null,
     *     business_name: string,
     *     trade_name?: string|null,
     *     registration_number?: string|null,
     *     business_address?: string|null,
     *     barangay?: string|null,
     *     ownership_type?: string|null,
     *     organization_name?: string|null,
     *     occupancy?: string|null,
     *     building_name?: string|null,
     *     property_index_number?: string|null,
     *     business_area_square_meters?: numeric-string|int|float|null,
     *     male_employee_count?: int|null,
     *     female_employee_count?: int|null,
     *     business_contact_number?: string|null,
     *     business_email?: string|null,
     *     established_on?: string|null,
     *     started_on?: string|null,
     *     registered_on?: string|null,
     *     application_number?: string|null,
     *     type: string,
     *     application_year: int,
     *     lines: list<array{
     *         line_of_business_id: int,
     *         declared_gross_sales_cents: int,
     *         capital_investment_cents: int,
     *         quantity: int,
     *         started_on?: string|null
     *     }>
     * } $data
     */
    public function handle(array $data, User $submittedBy): PermitApplication
    {
        return DB::transaction(function () use ($data, $submittedBy): PermitApplication {
            $owner = BusinessOwner::query()->create([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'] ?? null,
                'phone' => $data['owner_phone'] ?? null,
                'address' => $data['owner_address'] ?? null,
            ]);

            $business = Business::query()->create([
                'business_owner_id' => $owner->id,
                'name' => $data['business_name'],
                'trade_name' => $data['trade_name'] ?? null,
                'registration_number' => $data['registration_number'] ?? null,
                'address' => $data['business_address'] ?? null,
                'barangay' => $data['barangay'] ?? null,
                'ownership_type' => $data['ownership_type'] ?? null,
                'organization_name' => $data['organization_name'] ?? null,
                'occupancy' => $data['occupancy'] ?? null,
                'building_name' => $data['building_name'] ?? null,
                'property_index_number' => $data['property_index_number'] ?? null,
                'business_area_square_meters' => $data['business_area_square_meters'] ?? null,
                'male_employee_count' => $data['male_employee_count'] ?? null,
                'female_employee_count' => $data['female_employee_count'] ?? null,
                'contact_number' => $data['business_contact_number'] ?? null,
                'email' => $data['business_email'] ?? null,
                'established_on' => $data['established_on'] ?? null,
                'started_on' => $data['started_on'] ?? null,
                'registered_on' => $data['registered_on'] ?? null,
            ]);

            $permitApplication = PermitApplication::query()->create([
                'business_id' => $business->id,
                'submitted_by_id' => $submittedBy->id,
                'application_number' => $data['application_number'] ?? null,
                'type' => $data['type'],
                'status' => PermitApplicationStatus::Draft,
                'application_year' => $data['application_year'],
                'submitted_at' => now(),
                'metadata' => [
                    ...($this->metadataFor(PermitApplicationType::from($data['type'])) ?? []),
                    'applicant_declaration_draft' => $this->buildDeclarationDraft->handle($data),
                ],
            ]);

            foreach ($data['lines'] as $line) {
                $permitApplication->lines()->create($line);
            }

            $this->freezeDeclaration->handle($permitApplication, $submittedBy);

            return $permitApplication->load(['business.owner', 'lines.lineOfBusiness']);
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function metadataFor(PermitApplicationType $type): ?array
    {
        $renewalPolicyBoundary = $this->describeRenewalPolicyBoundary->handle($type);

        if ($renewalPolicyBoundary === null) {
            $amendmentPolicyBoundary = $this->describeAmendmentPolicyBoundary->handle($type);

            if ($amendmentPolicyBoundary === null) {
                $transferPolicyBoundary = $this->describeTransferPolicyBoundary->handle($type);

                if ($transferPolicyBoundary === null) {
                    $retirementPolicyBoundary = $this->describeRetirementPolicyBoundary->handle($type);

                    if ($retirementPolicyBoundary === null) {
                        return null;
                    }

                    return [
                        'retirement_policy_boundary' => $retirementPolicyBoundary,
                    ];
                }

                return [
                    'transfer_policy_boundary' => $transferPolicyBoundary,
                ];
            }

            return [
                'amendment_policy_boundary' => $amendmentPolicyBoundary,
            ];
        }

        return [
            'renewal_policy_boundary' => $renewalPolicyBoundary,
        ];
    }
}
