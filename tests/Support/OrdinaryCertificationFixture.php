<?php

use App\Actions\IssueManualCollectionReceipt;
use App\Assessment\AssessmentSnapshotFingerprint;
use App\Enums\AssessmentDecisionAction;
use App\Enums\PermitApplicationStatus;
use App\Models\Assessment;
use App\Models\AssessmentDecision;
use App\Models\BploRoutingDetermination;
use App\Models\BploRoutingWork;
use App\Models\CollectionAllocation;
use App\Models\InstitutionalPosition;
use App\Models\InstitutionalPositionAssignment;
use App\Models\PaperlessPaymentOrder;
use App\Models\PaymentSchedule;
use App\Models\PaymentScheduleLine;
use App\Models\Permission;
use App\Models\PermitApplication;
use App\Models\Role;
use App\Models\TreasuryCollection;
use App\Models\User;

function certificationOfficer(string $office): User
{
    $user = User::factory()->create();
    foreach (['staff.access', 'business_permit_evaluations.view'] as $code) {
        Permission::query()->firstOrCreate(['code' => $code], ['name' => $code, 'guard_name' => 'web']);
    }
    $role = Role::factory()->create(['code' => $office, 'name' => $office]);
    $user->roles()->attach($role);
    $role->permissions()->sync(Permission::whereIn('code', ['staff.access', 'business_permit_evaluations.view'])->pluck('id'));
    $position = InstitutionalPosition::factory()->for($role, 'capabilityRole')->create();
    InstitutionalPositionAssignment::query()->create(['user_id' => $user->id, 'institutional_position_id' => $position->id, 'status' => 'active', 'assigned_at' => now(), 'reason' => 'Synthetic UAT test']);

    return $user->fresh();
}

function ordinaryCertificationFixture(bool $issueAll = true): array
{
    config(['app.url' => 'http://localhost', 'stakeholder_preview.mode' => true, 'stakeholder_preview.production_migration_enabled' => false, 'stakeholder_preview.production_integrations' => 'disabled']);
    $application = PermitApplication::factory()->withStatus(PermitApplicationStatus::PendingPayment)->create(['metadata' => [], 'submitted_at' => now()]);
    $assessment = Assessment::factory()->for($application)->create(['total_amount_cents' => 417500]);
    AssessmentDecision::factory()->for($assessment)->create(['action' => AssessmentDecisionAction::Approved, 'total_amount_cents' => 417500, 'assessment_snapshot_hash' => app(AssessmentSnapshotFingerprint::class)->hash($assessment)]);
    $schedule = PaymentSchedule::factory()->for($application, 'permitApplication')->for($assessment)->create(['total_amount_cents' => 417500, 'paid_amount_cents' => 417500]);
    $collection = TreasuryCollection::factory()->for($application, 'permitApplication')->for($assessment)->for($schedule)->create(['amount_cents' => 417500]);
    $routing = BploRoutingDetermination::factory()->for($application, 'permitApplication')->create();
    foreach (['assessor', 'engineering', 'health', 'menro'] as $office) {
        $work = BploRoutingWork::factory()->create(['bplo_routing_determination_id' => $routing->id, 'office_code' => $office, 'office_label' => $office]);
        PaperlessPaymentOrder::factory()->create(['permit_application_id' => $application->id, 'bplo_routing_work_id' => $work->id, 'status' => 'issued']);
    }
    $groups = ['office:assessor' => 10000, 'office:engineering' => 15000, 'office:health' => 30000, 'office:menro' => 250000, 'treasury:lob:360' => 100000, 'treasury:application' => 12500];
    foreach ($groups as $group => $amount) {
        $line = PaymentScheduleLine::factory()->for($schedule)->create(['amount_cents' => $amount, 'paid_amount_cents' => $amount]);
        CollectionAllocation::factory()->for($collection)->for($line)->create(['receipt_group_key' => $group, 'receipt_group_label' => $group, 'amount_cents' => $amount]);
    }
    $application->update(['metadata' => ['nelson_reconciliation_v1' => ['commissioned_path' => true]]]);
    foreach (array_keys($groups) as $i => $group) {
        if (! $issueAll && $i === 5) {
            break;
        }
        app(IssueManualCollectionReceipt::class)->handle($collection, ['receipt_group_key' => $group, 'receipt_number' => (string) (7900001 + $i), 'numbering_authority' => 'manual']);
    }

    return [$application->fresh(), $collection->fresh(), $schedule->fresh(), $assessment->fresh()];
}
