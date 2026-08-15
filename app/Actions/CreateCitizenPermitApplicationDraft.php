<?php

namespace App\Actions;

use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\PermitApplication;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class CreateCitizenPermitApplicationDraft
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $submittedBy): PermitApplication
    {
        return DB::transaction(function () use ($data, $submittedBy): PermitApplication {
            $citizen = User::query()->lockForUpdate()->findOrFail($submittedBy->id);
            $owner = $this->resolveOwner($citizen, $data);
            $submittedBy->forceFill(['business_owner_id' => $owner->id]);
            $business = $this->resolveBusiness($owner, $data);

            $permitApplication = PermitApplication::query()->create([
                'business_id' => $business->id,
                'submitted_by_id' => $citizen->id,
                'application_number' => null,
                'type' => PermitApplicationType::New,
                'status' => PermitApplicationStatus::Draft,
                'application_year' => $data['application_year'],
                'submitted_at' => null,
                'metadata' => [
                    'citizen_intake' => [
                        'registry_owner_id' => $owner->id,
                        'saved_as_draft' => true,
                    ],
                ],
            ]);

            foreach ($data['lines'] as $line) {
                $permitApplication->lines()->create($line);
            }

            return $permitApplication->load(['business.owner', 'lines.lineOfBusiness']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveOwner(User $citizen, array $data): BusinessOwner
    {
        if ($citizen->business_owner_id !== null) {
            return BusinessOwner::query()->findOrFail($citizen->business_owner_id);
        }

        $owner = BusinessOwner::query()->create([
            'name' => $data['owner_name'],
            'email' => $data['owner_email'] ?? null,
            'phone' => $data['owner_phone'] ?? null,
            'address' => $data['owner_address'] ?? null,
        ]);

        $citizen->forceFill(['business_owner_id' => $owner->id])->save();

        return $owner;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveBusiness(BusinessOwner $owner, array $data): Business
    {
        if (isset($data['business_id'])) {
            $business = $owner->businesses()->find($data['business_id']);

            if ($business === null) {
                throw new DomainException('The selected business does not belong to the citizen registry identity.');
            }

            return $business;
        }

        return Business::query()->create([
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
    }
}
