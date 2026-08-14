<?php

namespace App\Actions;

use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Models\PermitApplication;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class UpdateCitizenPermitApplicationDraft
{
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
     *     type: string,
     *     application_year: int,
     *     draft_version: string,
     *     lines: list<array{
     *         line_of_business_id: int,
     *         declared_gross_sales_cents: int,
     *         capital_investment_cents: int,
     *         quantity: int,
     *         started_on?: string|null
     *     }>
     * } $data
     */
    public function handle(PermitApplication $permitApplication, array $data, User $editedBy): PermitApplication
    {
        return DB::transaction(function () use ($permitApplication, $data, $editedBy): PermitApplication {
            $draft = PermitApplication::query()
                ->with(['business.owner'])
                ->lockForUpdate()
                ->findOrFail($permitApplication->id);

            if ($draft->submitted_by_id !== $editedBy->id) {
                throw new DomainException('This permit application draft does not belong to the authenticated citizen.');
            }

            if ($draft->status !== PermitApplicationStatus::Draft) {
                throw new DomainException('Only draft permit applications may be edited by a citizen.');
            }

            if ($draft->type !== PermitApplicationType::New) {
                throw new DomainException('Only new permit application drafts may be edited through citizen intake.');
            }

            if ($draft->application_number !== null || $draft->assessments()->exists()) {
                throw new DomainException('This draft has entered municipal processing and may no longer be edited by the citizen.');
            }

            if (
                $draft->business->permitApplications()->whereKeyNot($draft->id)->exists()
                || $draft->business->owner->businesses()->whereKeyNot($draft->business_id)->exists()
            ) {
                throw new DomainException('This draft uses shared registry records and cannot be edited through citizen intake.');
            }

            if (! CarbonImmutable::parse($data['draft_version'])->equalTo($draft->updated_at)) {
                throw new DomainException('This draft changed after it was opened. Reload the latest version before saving.');
            }

            $draft->business->owner->update([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'] ?? null,
                'phone' => $data['owner_phone'] ?? null,
                'address' => $data['owner_address'] ?? null,
            ]);

            $draft->business->update([
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

            $draft->lines()->delete();

            foreach ($data['lines'] as $line) {
                $draft->lines()->create($line);
            }

            $draft->forceFill([
                'application_year' => $data['application_year'],
            ])->save();
            $draft->touch();

            return $draft->refresh()->load(['business.owner', 'lines.lineOfBusiness']);
        });
    }
}
