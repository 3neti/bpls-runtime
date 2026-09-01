<?php

namespace App\Actions;

use App\Enums\PermitApplicationType;
use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LifecycleScenarioSpecimen;
use App\Models\PermitApplication;
use App\Models\User;
use LogicException;

class BuildBplsProductLabGuide
{
    public function __construct(private readonly InspectBplsInstallation $inspectInstallation) {}

    /** @return array<string, mixed> */
    public function handle(): array
    {
        $inspection = $this->inspectInstallation->handle();
        $newSpecimen = LifecycleScenarioSpecimen::query()
            ->where('scenario_id', NewApplicationHappyPathDefinition::Id)
            ->where('scenario_revision', NewApplicationHappyPathDefinition::Revision)
            ->with('permitApplication')
            ->sole();
        $renewalSpecimen = LifecycleScenarioSpecimen::query()
            ->where('scenario_id', RenewalHappyPathDefinition::Id)
            ->where('scenario_revision', RenewalHappyPathDefinition::Revision)
            ->with('permitApplication')
            ->sole();
        $newApplication = $newSpecimen->permitApplication;
        $renewal = $renewalSpecimen->permitApplication;

        $this->assert($newApplication->business_id === $renewal->business_id, 'Scenario applications do not reuse one Business.');
        $this->assert($newApplication->type === PermitApplicationType::New && $newApplication->application_year === 2025, 'Scenario 01 is not the effective-2025 New Business Permit.');
        $this->assert($renewal->type === PermitApplicationType::Renewal && $renewal->application_year === 2026, 'Scenario 02 is not the effective-2026 Renewal.');
        $this->assert(BusinessOwner::query()->count() === 1, 'Product lab does not contain exactly one BusinessOwner.');
        $this->assert(Business::query()->count() === 1, 'Product lab does not contain exactly one Business.');
        $this->assert(PermitApplication::query()->count() === 2, 'Product lab does not contain exactly two permit applications.');
        $this->assert($inspection['integrity']['pass'], 'BPLS installation integrity is not passing.');
        $this->assert($inspection['price_list']['synthetic_uat_exact_published_count'] === 0, 'Synthetic scenario prices leaked into the commissioned Price List.');

        $business = $renewal->business()->with('owner')->sole();
        $citizen = User::query()->where('email', 'scenario-citizen@example.test')->sole();
        $newAssessment = $newApplication->assessments()->whereNull('superseded_at')->sole();
        $renewalAssessment = $renewal->assessments()->whereNull('superseded_at')->sole();
        $newPayable = $newApplication->paymentSchedules()->sole();
        $renewalPayable = $renewal->paymentSchedules()->sole();

        $this->assert($citizen->business_owner_id === $business->business_owner_id, 'Scenario Citizen is not linked to the one BusinessOwner.');
        $this->assert($newPayable->paid_amount_cents === 0 && $renewalPayable->paid_amount_cents === 0, 'Product lab must not invent prior payment.');

        return [
            'institution' => [
                'name' => $inspection['municipality']['name'],
                'price_list' => $inspection['price_list']['coherent'] ? 'PASS' : 'FAIL',
                'business_inspection_fee_cents' => 35_000,
                'synthetic_prices_published' => $inspection['price_list']['synthetic_uat_exact_published_count'],
            ],
            'citizen' => ['id' => $citizen->id, 'name' => $citizen->name],
            'owner' => ['id' => $business->owner->id, 'name' => $business->owner->name],
            'business' => ['id' => $business->id, 'name' => $business->name],
            'applications' => [
                ['id' => $newApplication->id, 'year' => 2025, 'type' => 'New Business Permit', 'assessment_cents' => $newAssessment->total_amount_cents, 'amount_due_cents' => $newPayable->total_amount_cents - $newPayable->paid_amount_cents],
                ['id' => $renewal->id, 'year' => 2026, 'type' => 'Renewal', 'assessment_cents' => $renewalAssessment->total_amount_cents, 'amount_due_cents' => $renewalPayable->total_amount_cents - $renewalPayable->paid_amount_cents],
            ],
            'inventory' => [
                'business_owners' => BusinessOwner::query()->count(),
                'businesses' => Business::query()->count(),
                'permit_applications' => PermitApplication::query()->count(),
            ],
            'links' => [
                'stakeholder_preview' => route('home'),
                'citizen_profile' => route('citizen.profile.show'),
                'business' => route('citizen.businesses.show', $business),
                'new_application' => route('citizen.permit-applications.show', $newApplication),
                'renewal_application' => route('citizen.permit-applications.show', $renewal),
                'bplo' => route('staff.permit-applications.show', $renewal),
                'concerned_offices' => route('staff.permit-applications.evaluation.show', $renewal),
                'assessment_officer_assessment' => route('staff.permit-applications.assessments.show', $renewalAssessment),
                'treasury' => route('staff.permit-applications.evaluation.show', $renewal),
                'municipal_treasurer' => route('staff.permit-applications.assessments.show', $renewalAssessment),
                'payable' => route('staff.payment-schedules.show', $renewalPayable),
                'citizen_services_and_fees' => route('citizen.services-and-fees.index'),
            ],
            'notes' => [
                'All product-lab identities and transactions are synthetic.',
                'Scenario prices are provisional, noncommissioned, and absent from the public Price List; the ₱350 Business Inspection Fee is governed.',
                'Scenario-effective dates are deterministic; execution timestamps are actual.',
                'QR settlement and permit release are not certified, and no prior payment or release was invented.',
                'No real registry import or account claiming was performed.',
                'No Cloud, UAT, production system, or production integration was touched.',
            ],
        ];
    }

    private function assert(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new LogicException($message);
        }
    }
}
