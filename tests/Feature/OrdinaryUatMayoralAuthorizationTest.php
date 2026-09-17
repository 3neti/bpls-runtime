<?php

use App\Actions\BuildMunicipalWorkInbox;
use App\Actions\BuildPublicPermitVerificationProjection;
use App\Actions\DescribePermitVerificationBoundary;
use App\Actions\OrdinaryUatPermitAuthority;
use App\Actions\ProjectPermitReadiness;
use App\Actions\RecordOrdinaryUatMayoralAuthorization;
use App\Actions\RecordPostPaymentOfficeCertification;
use App\Assessment\Price\CanonicalFinancialFingerprint;
use App\Data\Application\ApplicationDataResolver;
use App\Models\InstitutionalPositionAssignment;
use App\Models\Permission;
use App\Models\PermitApplication;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../Support/OrdinaryCertificationFixture.php';

function mayoralFixture(): array
{
    [$a, $c, $s, $assessment] = ordinaryCertificationFixture();
    $report = ['components' => [['exact_once_key' => 'synthetic-fixture', 'key' => 'uat', 'label' => 'UAT total', 'resolved_minor' => 417500]], 'total' => ['minor' => 417500, 'currency' => 'PHP']];
    $assessment->update(['price_report_snapshot' => $report, 'price_report_fingerprint' => app(CanonicalFinancialFingerprint::class)->hash($report)]);
    foreach ($a->postPaymentOfficeCertifications as $cert) {
        $officer = certificationOfficer($cert->office_code);
        app(RecordPostPaymentOfficeCertification::class)->handle($cert, $officer, 'certified');
    }
    $mayor = certificationOfficer('mayor_office');
    $releasing = certificationOfficer('releasing');
    foreach (['mayor' => $mayor, 'releasing' => $releasing] as $key => $actor) {
        $assignment = InstitutionalPositionAssignment::where('user_id', $actor->id)->sole();
        $assignment->position->update(['code' => $key === 'mayor' ? 'mayors_office_reviewer' : 'releasing_officer']);
        config(['workflow_uat_authority.'.$key.'_assignment_id' => $assignment->id]);
    }
    config(['workflow_uat_authority.mode' => 'synthetic_only']);

    return [$a->fresh(), $mayor, $releasing, $c, $s, $assessment];
}

test('ordinary Mayor separately authorizes issues and releases once with immutable financial evidence', function () {
    [$a, $mayor, $releasing, $c, $s, $assessment] = mayoralFixture();
    $snapshot = fn () => [$a->fresh()->getRawOriginal(), $c->fresh()->getRawOriginal(), $s->fresh()->getRawOriginal(), $assessment->fresh()->getRawOriginal(), $c->receipts()->orderBy('id')->get()->map->getRawOriginal()->all(), $c->allocations()->orderBy('id')->get()->map->getRawOriginal()->all(), $a->postPaymentOfficeCertifications()->orderBy('id')->get()->map->getRawOriginal()->all()];
    $before = $snapshot();
    $publicProjection = app(BuildPublicPermitVerificationProjection::class);
    $incomplete = $publicProjection->handle($a->fresh());
    expect($incomplete['availability']['status'])->toBe($incomplete['release_readiness']['authority_boundary']['status']);
    $route = route('staff.ordinary-uat-permit.store', $a);
    expect(data_get($a->metadata, 'lifecycle_cleanroom.run_id'))->toBeNull()
        ->and(app(ProjectPermitReadiness::class)->handle($a)['blocked_by'])->toBe(['mayoral_authorization_recorded'])
        ->and(app(BuildMunicipalWorkInbox::class)->handle($mayor)['items']->where('task_type', 'permit_issuance'))->toHaveCount(1);
    $inbox = $this->actingAs($mayor)->get(route('staff.work.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('work-inbox/Index')
            ->has('workItems.data', 1)
            ->where('workItems.data.0.task_label', 'Authorize Business Permit issuance')
            ->where('workItems.data.0.action_url', route('staff.ordinary-uat-permit.show', $a, false)));
    $taskUrl = $inbox->viewData('page')['props']['workItems']['data'][0]['action_url'];
    expect($taskUrl)->not->toBe(route('staff.permit-applications.show', $a, false));
    $this->get($taskUrl)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('ordinary-uat-permit/Show')
            ->where('action', 'authorize')
            ->where('readiness.blocked_by', ['mayoral_authorization_recorded'])
            ->where('readiness.receipt_total_cents', 417500)
            ->where('readiness.prerequisites.all_required_post_payment_certifications', true)
            ->where('readiness.semantic_classification', 'synthetic_only')
            ->where('readiness.production_authority', false)
            ->where('applicationUrl', route('staff.permit-applications.evaluation.show', $a, false)));
    $projection = app(ApplicationDataResolver::class)->resolve($a->fresh(), $mayor)->toArray();
    $note = collect($projection['actor_context']['work_notes'])->firstWhere('id', 'permit_authority_review');
    expect($note['actionable'])->toBeTrue()->and($note['state_label'])->toBe('Ready for Mayoral Authorization')
        ->and($note['action_url'])->toBe(route('staff.ordinary-uat-permit.show', $a, false));
    $this->get(route('staff.permit-applications.evaluation.show', $a))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('business-permit-evaluations/Show')
            ->where('applicationData.actor_context.work_notes', fn ($notes) => collect($notes)
                ->contains(fn ($note) => $note['id'] === 'permit_authority_review'
                    && $note['actionable'] === true
                    && $note['state_label'] === 'Ready for Mayoral Authorization'
                    && $note['action_url'] === $taskUrl)));
    $this->post($route, ['ceremony' => 'issue'])->assertSessionHasErrors('ceremony');
    expect($a->provisionalUatPermitCompletion()->count())->toBe(0);
    $this->post($route, ['ceremony' => 'authorize'])->assertRedirect();
    $record = $a->provisionalUatPermitCompletion()->sole();
    $frozen = $record->getRawOriginal();
    $evidence = $record->source_snapshot['ordinary_mayoral_authorization'];
    expect($record->issued_at)->toBeNull()->and($record->released_at)->toBeNull()
        ->and($evidence['production_authority'])->toBeFalse()
        ->and($evidence['mode'])->toBe('synthetic_only')->and($evidence['actor_id'])->toBe($mayor->id)
        ->and($evidence['configuration']['assignment_id'])->toBe(config('workflow_uat_authority.mayor_assignment_id'));
    $this->post($route, ['ceremony' => 'authorize'])->assertRedirect();
    $this->get(route('staff.work.index'))->assertOk()->assertInertia(fn (Assert $p) => $p
        ->has('workItems.data', 1)->where('workItems.data.0.task_label', 'Issue UAT Business Permit')
        ->where('workItems.data.0.action_url', $taskUrl));
    $this->get($taskUrl)->assertOk()->assertInertia(fn (Assert $p) => $p->where('action', 'issue')->where('readiness.ready', true));
    expect($record->fresh()->getRawOriginal())->toBe($frozen);
    $this->actingAs($releasing)->post($route, ['ceremony' => 'release'])->assertSessionHasErrors('ceremony');
    $this->actingAs($mayor)->post($route, ['ceremony' => 'issue'])->assertRedirect();
    $issued = $record->fresh()->getRawOriginal();
    $pendingRelease = $publicProjection->handle($a->fresh());
    expect($pendingRelease['availability']['status'])->toBe('issued_synthetic')
        ->and($pendingRelease['availability']['title'])->toContain('awaiting release')
        ->and($pendingRelease['release_readiness'])->toBeNull();
    $this->post($route, ['ceremony' => 'issue'])->assertRedirect();
    expect($record->fresh()->getRawOriginal())->toBe($issued)
        ->and($record->fresh()->source_snapshot['ordinary_mayoral_authorization'])->toBe($evidence)
        ->and($record->fresh()->released_at)->toBeNull();
    $verification = app(DescribePermitVerificationBoundary::class)->handle($a->fresh());
    $this->get($verification['url'])->assertNotFound();
    $this->get(route('staff.work.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->has('workItems.data', 0));
    $this->get($taskUrl)->assertOk()->assertInertia(fn (Assert $p) => $p->where('action', null));
    $releaseInbox = $this->actingAs($releasing)->get(route('staff.work.index'))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->has('workItems.data', 1)
            ->where('workItems.data.0.task_type', 'permit_release')
            ->where('workItems.data.0.action_url', $taskUrl));
    $this->get($releaseInbox->viewData('page')['props']['workItems']['data'][0]['action_url'])
        ->assertOk()->assertInertia(fn (Assert $p) => $p->component('ordinary-uat-permit/Show')->where('action', 'release'));
    $this->actingAs($releasing)->post($route, ['ceremony' => 'release'])->assertRedirect();
    $released = $record->fresh()->getRawOriginal();
    $this->post($route, ['ceremony' => 'release'])->assertRedirect();
    expect($record->fresh()->getRawOriginal())->toBe($released)
        ->and($a->provisionalUatPermitCompletion()->count())->toBe(1)
        ->and($snapshot())->toBe($before)
        ->and($c->receipts()->sum('amount_cents'))->toBe(417500);
    $this->get($verification['url'])->assertOk();
    $public = $this->getJson($verification['url'])->assertOk()
        ->assertJsonPath('availability.status', 'released_synthetic')
        ->assertJsonPath('release_readiness', null)
        ->assertJsonPath('permit.production_authority', false)
        ->assertJsonPath('permit.legal_effect', false)
        ->assertJsonPath('permit.issuing_authority.signature_applied', false)
        ->assertJsonPath('verification.legal_release_confirmed', false)
        ->assertJsonPath('verification.legal_effect_confirmed', false)
        ->assertJsonMissingPath('permit.official_receipts')
        ->assertJsonMissingPath('permit.official_receipt_number')
        ->assertJsonMissingPath('preview_completion.source_snapshot')
        ->json();
    expect($public['availability']['note'])->toContain('No statutory signature or legal effect')
        ->and(json_encode($public))->not->toContain('awaiting_prerequisites', 'release remains unavailable', $evidence['fingerprint']);
    foreach ($c->receipts as $receipt) {
        expect(json_encode($public))->not->toContain($receipt->receipt_number);
    }
    $this->get($verification['view_url'])->assertOk()->assertInertia(fn (Assert $p) => $p
        ->component('public/PermitVerification')
        ->where('availability.status', 'released_synthetic')
        ->where('availability.fact_value', 'Synthetic UAT only')
        ->where('releaseReadiness', null)
        ->where('permit.current_stage', 'released_synthetic'));
    expect($this->getJson($verification['url'])->assertOk()->json())->toBe($public)
        ->and($record->fresh()->getRawOriginal())->toBe($released)
        ->and($snapshot())->toBe($before);
    $this->get(route('staff.work.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->has('workItems.data', 0));
    $this->get($taskUrl)->assertOk()->assertInertia(fn (Assert $p) => $p->where('action', null));
    expect(fn () => $record->fresh()->update(['decided_by_id' => $releasing->id]))->toThrow(LogicException::class);
});

test('ordinary permit task deep links enforce the existing actor and application boundaries', function () {
    [$a, $mayor] = mayoralFixture();
    $url = route('staff.ordinary-uat-permit.show', $a);
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs(User::role('engineering')->sole())->get($url)->assertForbidden();
    $this->actingAs($mayor)->get(route('staff.ordinary-uat-permit.show', PermitApplication::factory()->create()))->assertForbidden();
    $this->get(route('staff.ordinary-uat-permit.show', 999999))->assertNotFound();
    config(['app.url' => 'https://historical-uat.laravel.cloud']);
    $this->get($url)->assertForbidden();
    config(['app.url' => 'http://localhost']);
    app()->instance('env', 'production');
    $this->get($url)->assertForbidden();
    expect($a->provisionalUatPermitCompletion()->count())->toBe(0);
});

test('assigned preview Mayor can open the authorized application evidence link read-only', function () {
    [$a, $mayor] = mayoralFixture();
    app(RecordOrdinaryUatMayoralAuthorization::class)->handle($a, $mayor);

    $mayor->roles()->detach();
    $previewRole = Role::factory()->create(['code' => 'preview_mayor_office']);
    $previewRole->syncPermissions([Permission::query()->where('code', 'staff.access')->firstOrFail()]);
    $mayor->roles()->attach($previewRole);
    $mayor->refresh();

    $this->actingAs($mayor)
        ->get(route('staff.permit-applications.evaluation.show', $a))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('business-permit-evaluations/Show')
            ->where('can.initialize', false));
});

test('ordinary Mayoral authority fails closed at actor environment and evidence boundaries', function (string $damage) {
    [$a, $mayor, $releasing, $c] = mayoralFixture();
    match ($damage) {
        'wrong_actor' => $mayor = $releasing,
        'ended_assignment' => InstitutionalPositionAssignment::whereKey(config('workflow_uat_authority.mayor_assignment_id'))->update(['ended_at' => now()]),
        'missing_configuration' => config(['workflow_uat_authority.mode' => null]),
        'historical_environment' => config(['app.url' => 'https://historical-uat.laravel.cloud']),
        'production' => app()->instance('env', 'production'),
        'missing_certification' => $a->postPaymentOfficeCertifications()->first()->update(['status' => 'pending']),
        'wrong_receipt_amount' => $c->receipts()->first()->update(['amount_cents' => 1]),
        'missing_receipt' => $c->receipts()->first()->update(['status' => 'voided']),
    };
    if ($damage === 'production') {
        expect(fn () => app(RecordOrdinaryUatMayoralAuthorization::class)->handle($a, $mayor))->toThrow(DomainException::class);
        expect($a->provisionalUatPermitCompletion()->count())->toBe(0);

        return;
    }
    $response = $this->actingAs($mayor)->post(route('staff.ordinary-uat-permit.store', $a), ['ceremony' => 'authorize']);
    if (in_array($damage, ['missing_certification', 'wrong_receipt_amount', 'missing_receipt'], true)) {
        $response->assertSessionHasErrors('ceremony');
    } else {
        $response->assertForbidden();
    }
    expect($a->provisionalUatPermitCompletion()->count())->toBe(0)
        ->and(app(BuildMunicipalWorkInbox::class)->handle($mayor)['items']->where('task_type', 'permit_issuance'))->toHaveCount(0);
})->with(['wrong_actor', 'ended_assignment', 'missing_configuration', 'historical_environment', 'production', 'missing_certification', 'wrong_receipt_amount', 'missing_receipt']);

test('an authorization cannot be transplanted to another Application', function () {
    [$a, $mayor] = mayoralFixture();
    app(RecordOrdinaryUatMayoralAuthorization::class)->handle($a, $mayor);
    $other = PermitApplication::factory()->create();
    $other->setRelation('provisionalUatPermitCompletion', $a->provisionalUatPermitCompletion()->sole());
    expect(app(OrdinaryUatPermitAuthority::class)->authorized($other))->toBeFalse();
});

test('issuance refuses certification evidence drift after authorization without rewriting the authorization', function () {
    [$a, $mayor] = mayoralFixture();
    $authorization = app(RecordOrdinaryUatMayoralAuthorization::class)->handle($a, $mayor);
    $frozen = $authorization->fresh()->getRawOriginal();
    $a->postPaymentOfficeCertifications()->first()->update(['remarks' => 'Changed after authorization in synthetic test']);
    $this->actingAs($mayor)->post(route('staff.ordinary-uat-permit.store', $a), ['ceremony' => 'issue'])->assertSessionHasErrors('ceremony');
    expect($authorization->fresh()->getRawOriginal())->toBe($frozen)->and($authorization->fresh()->issued_at)->toBeNull();
});
