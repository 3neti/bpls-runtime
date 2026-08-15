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
                $editedBy->business_owner_id === null
                || $draft->business->business_owner_id !== $editedBy->business_owner_id
            ) {
                throw new DomainException('This draft is not linked to the citizen registry identity.');
            }

            if (! CarbonImmutable::parse($data['draft_version'])->equalTo($draft->updated_at)) {
                throw new DomainException('This draft changed after it was opened. Reload the latest version before saving.');
            }

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
