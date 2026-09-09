<?php

use App\Actions\ProvisionLifecycleLaboratoryActors;
use App\Enums\PermitApplicationStatus;
use App\Models\BploRoutingDetermination;
use App\Models\BploRoutingWork;
use App\Models\InstitutionalPositionAssignment;
use App\Models\PaperlessPaymentOrder;
use App\Models\PermitApplication;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Artisan::call('bpls:install');
});

test('staff inbox is scoped to the actors active municipal position and routed office', function () {
    $actors = app(ProvisionLifecycleLaboratoryActors::class)->handle();
    $application = PermitApplication::factory()
        ->withStatus(PermitApplicationStatus::Assessment)
        ->create(['submitted_at' => now()]);
    $routing = BploRoutingDetermination::factory()->for($application)->create();
    BploRoutingWork::factory()->for($routing, 'determination')->create([
        'office_code' => 'engineering',
        'office_label' => 'Municipal Engineering Office',
    ]);
    $healthWork = BploRoutingWork::factory()->for($routing, 'determination')->create([
        'office_code' => 'health',
        'office_label' => 'Municipal Health Office',
    ]);

    $this->actingAs($actors['health'])
        ->get(route('staff.work.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('work-inbox/Index')
            ->has('workItems.data', 1)
            ->where('workItems.data.0.task_type', 'payment_order')
            ->where('workItems.data.0.office_label', 'Municipal Health Office')
            ->where('workItems.data.0.application.id', $application->id)
            ->where('workItems.data.0.action_url', route('staff.permit-applications.evaluation.show', $application, false))
            ->where('counts.action_required', 1)
        );

    PaperlessPaymentOrder::factory()->for($healthWork, 'routingWork')->create([
        'permit_application_id' => $application->id,
        'issued_by_id' => $actors['health']->id,
    ]);

    $this->actingAs($actors['health'])
        ->get(route('staff.work.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('workItems.data', 0)
            ->where('counts.action_required', 0)
        );

    $this->actingAs($actors['engineering'])
        ->get(route('staff.work.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('workItems.data', 1)
            ->where('workItems.data.0.office_label', 'Municipal Engineering Office')
        );
});

test('revoked position assignment removes work from the inbox', function () {
    $actors = app(ProvisionLifecycleLaboratoryActors::class)->handle();
    PermitApplication::factory()
        ->withStatus(PermitApplicationStatus::Assessment)
        ->create(['submitted_at' => now()]);

    $this->actingAs($actors['intake'])
        ->get(route('staff.work.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('workItems.data', 1)
            ->where('workItems.data.0.task_type', 'bplo_routing')
        );

    InstitutionalPositionAssignment::query()
        ->where('user_id', $actors['intake']->id)
        ->where('status', 'active')
        ->update(['status' => 'ended', 'ended_at' => now()]);

    $this->actingAs($actors['intake'])
        ->get(route('staff.work.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('workItems.data', 0)
            ->has('assignments', 0)
        );
});

test('citizens cannot open the municipal work inbox', function () {
    $actors = app(ProvisionLifecycleLaboratoryActors::class)->handle();

    $this->actingAs($actors['citizen'])
        ->get(route('staff.work.index'))
        ->assertForbidden();
});
