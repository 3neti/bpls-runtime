<?php

namespace App\Actions;

use App\Models\PermitApplication;
use App\Models\PermitApplicationDeclaration;
use App\Models\User;
use Illuminate\Support\Arr;

class FreezePermitApplicationDeclaration
{
    public function __construct(private readonly BuildPermitApplicationDeclarationDraft $buildDraft) {}

    public function handle(PermitApplication $permitApplication, ?User $declaredBy = null): PermitApplicationDeclaration
    {
        $application = PermitApplication::query()
            ->with(['business.owner', 'lines.lineOfBusiness', 'declaration'])
            ->lockForUpdate()
            ->findOrFail($permitApplication->id);

        if ($application->declaration instanceof PermitApplicationDeclaration) {
            return $application->declaration;
        }

        $draft = data_get($application->metadata, 'applicant_declaration_draft');
        if (! is_array($draft)) {
            $draft = $this->buildDraft->handle([
                'application_year' => $application->application_year,
                'type' => $application->type->value,
                'date_of_application' => $application->submitted_at?->toDateString() ?? now()->toDateString(),
                'owner_name' => $application->business->owner->name,
                'owner_email' => $application->business->owner->email,
                'owner_phone' => $application->business->owner->phone,
                'owner_address' => $application->business->owner->address,
                'business_name' => $application->business->name,
                'trade_name' => $application->business->trade_name,
                'registration_number' => $application->business->registration_number,
                'registered_on' => $application->business->registered_on?->toDateString(),
                'business_address' => $application->business->address,
                'barangay' => $application->business->barangay,
                'ownership_type' => $application->business->ownership_type,
                'property_index_number' => $application->business->property_index_number,
                'business_area_square_meters' => $application->business->business_area_square_meters,
                'male_employee_count' => $application->business->male_employee_count,
                'female_employee_count' => $application->business->female_employee_count,
                'business_contact_number' => $application->business->contact_number,
                'business_email' => $application->business->email,
                'applicant_printed_name' => $application->business->owner->name,
                'position_title' => 'Owner',
                'undertaking_accepted' => true,
            ]);
        }

        $snapshot = [
            ...$draft,
            'lines_of_business' => $application->lines->map(fn ($line): array => [
                'code' => $line->lineOfBusiness?->code,
                'name' => $line->lineOfBusiness?->name,
                'number_of_units' => $line->quantity,
                'capitalization_cents' => $line->capital_investment_cents,
                'essential_gross_sales_cents' => $line->essential_gross_sales_cents,
                'non_essential_gross_sales_cents' => $line->non_essential_gross_sales_cents,
                'declared_gross_sales_cents' => $line->declared_gross_sales_cents,
            ])->values()->all(),
        ];
        $normalized = $this->normalize($snapshot);
        $hash = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return PermitApplicationDeclaration::query()->create([
            'permit_application_id' => $application->id,
            'declared_by_id' => $declaredBy instanceof User ? $declaredBy->id : $application->submitted_by_id,
            'schema_version' => (int) Arr::get($normalized, 'schema_version', 1),
            'snapshot_hash' => $hash,
            'snapshot' => $normalized,
            'declared_at' => $application->submitted_at ?? now(),
        ]);
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }
}
