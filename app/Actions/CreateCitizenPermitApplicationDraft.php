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
    public function __construct(
        private readonly BuildPermitApplicationDeclarationDraft $buildDeclarationDraft,
        private readonly BuildCitizenPermitApplicationLabFixture $buildLabFixture,
    ) {}

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
            $laboratoryReconciliation = $this->laboratoryReconciliation($data);

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
                    'applicant_declaration_draft' => $this->buildDeclarationDraft->handle([
                        'type' => PermitApplicationType::New->value,
                        'date_of_application' => now()->toDateString(),
                        'mode_of_payment' => 'annually',
                        'undertaking_accepted' => true,
                        'applicant_printed_name' => $data['owner_name'] ?? $submittedBy->name,
                        'position_title' => 'Owner',
                        ...$data,
                    ]),
                    ...($laboratoryReconciliation === null ? [] : [
                        'laboratory_assessment_reconciliation' => $laboratoryReconciliation,
                    ]),
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
     * @return array<string, mixed>|null
     */
    private function laboratoryReconciliation(array $data): ?array
    {
        $fixtureId = $data['lab_fixture_id'] ?? null;
        if (! is_string($fixtureId) || trim($fixtureId) === '') {
            return null;
        }

        $fixture = collect($this->buildLabFixture->pool())->firstWhere('fixture_id', $fixtureId);
        if (! is_array($fixture)
            || $fixture['classification'] !== 'authorized_legacy_source_lab_only'
            || $fixture['source_kind'] !== 'immutable_production_backup'
            || ! is_array($fixture['historical_assessment'])) {
            throw new DomainException('The selected legacy laboratory assessment evidence is unavailable.');
        }

        return [
            'schema_version' => 'bpls.laboratory-assessment-reconciliation.v1',
            'fixture_id' => $fixture['fixture_id'],
            'source_kind' => $fixture['source_kind'],
            'source_reference' => $fixture['source_reference'],
            'source_business_category' => $fixture['source_business_category'],
            'semantic_classification' => 'observational_legacy_financial_evidence',
            'historical_assessment' => $fixture['historical_assessment'],
            'component_identity_mapping' => 'not_established',
            'operational_authority' => false,
            'production_liability' => false,
        ];
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
            $business = $owner->businesses()->whereKey($data['business_id'])->first();

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
