<?php

namespace App\Actions;

use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Models\BusinessPermitEvaluation;
use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;
use Illuminate\Support\Collection;
use LogicException;

class BuildLifecycleOfficeReviewHandoff
{
    public function __construct(private readonly BusinessPermitEvaluationResolver $evaluationResolver) {}

    /** @return array<string, mixed> */
    public function handle(LifecycleCleanroomRun $run, int $applicationYear): array
    {
        $application = match ($applicationYear) {
            2025 => $run->newApplication()->first(),
            2026 => $run->renewalApplication()->first(),
            default => null,
        };

        if (! $application instanceof PermitApplication || ! in_array($application->id, $run->ownedPermitApplicationIds(), true)) {
            throw new LogicException('This cleanroom does not have an application for the requested office-review handoff.');
        }

        $application->load([
            'business.owner',
            'lines.lineOfBusiness',
            'bploRoutingDetermination.determinedBy',
            'bploRoutingDetermination.works.lineOfBusiness',
            'businessPermitEvaluation.currentVersion',
            'businessPermitEvaluation.items.revisions.version',
            'businessPermitEvaluation.items.revisions.actor',
        ]);
        $routing = $application->bploRoutingDetermination;
        $evaluation = $application->businessPermitEvaluation;

        if ($routing === null || ! $evaluation instanceof BusinessPermitEvaluation) {
            throw new LogicException('Office-review work has not been created for this application.');
        }

        $projection = $this->evaluationResolver->resolve($evaluation);
        $responsibilities = collect($projection['items'])
            ->filter(fn (array $item): bool => data_get($item, 'metadata.lifecycle_cleanroom_responsibility') === true)
            ->values();
        $resolvedCount = $responsibilities->where('resolution', 'resolved')->count();
        $openResponsibilities = $responsibilities->where('resolution', '!=', 'resolved');
        $routineDefaultCount = $openResponsibilities->filter(
            fn (array $item): bool => $item['item_type'] === 'charge'
                && is_int(data_get($item, 'default_value.amount_cents'))
                && data_get($item, 'metadata.inspection_required', false) === false,
        )->count();
        $offices = $routing->works
            ->groupBy('office_code')
            ->map(function (Collection $works, string $officeCode) use ($responsibilities, $run): array {
                $workIds = $works->pluck('id');
                $officeResponsibilities = $responsibilities
                    ->filter(fn (array $item): bool => $workIds->contains(data_get($item, 'metadata.bplo_routing_work_id')))
                    ->values();
                $resolvedCount = $officeResponsibilities->where('resolution', 'resolved')->count();
                $firstWork = $works->first();

                return [
                    'code' => $officeCode,
                    'label' => $firstWork->office_label,
                    'activity' => $works->pluck('lineOfBusiness.name')->filter()->unique()->join(', '),
                    'reason' => $works->pluck('situational_reason')->filter()->unique()->join(' '),
                    'required_work' => $works->pluck('required_work')->filter()->unique()->join(' '),
                    'status' => match (true) {
                        $officeResponsibilities->isNotEmpty() && $resolvedCount === $officeResponsibilities->count() => 'Complete',
                        $resolvedCount > 0 => 'In progress',
                        default => 'Not started',
                    },
                    'responsibility_count' => $officeResponsibilities->count(),
                    'resolved_count' => $resolvedCount,
                    'action_url' => route('stakeholder-preview.lifecycle-laboratory.cleanrooms.enter-actor', [$run, $officeCode], false),
                    'responsibilities' => $officeResponsibilities->map(fn (array $item): array => [
                        'label' => data_get($item, 'metadata.label', str($item['key'])->headline()->toString()),
                        'status' => $item['resolution'] === 'resolved' ? 'Determined' : 'Awaiting determination',
                    ])->all(),
                ];
            })
            ->sortBy(fn (array $office): int => match ($office['code']) {
                'assessor' => 0,
                'engineering' => 1,
                'health' => 2,
                'menro' => 3,
                default => 4,
            })
            ->values();
        $nextOfficeCode = data_get($offices->first(fn (array $office): bool => $office['status'] !== 'Complete'), 'code');
        $offices = $offices->map(fn (array $office): array => [
            ...$office,
            'is_next' => $office['code'] === $nextOfficeCode,
        ]);

        return [
            'run' => [
                'id' => $run->id,
                'public_id' => $run->public_id,
            ],
            'application' => [
                'id' => $application->id,
                'business_name' => $application->business->name,
                'owner_name' => $application->business->owner->name,
                'tracking_reference' => $application->tracking_reference,
                'type' => $application->type->value,
                'year' => $application->application_year,
                'submitted_at' => $application->submitted_at?->toIso8601String(),
                'activities' => $application->lines->map(fn ($line): array => [
                    'name' => $line->lineOfBusiness->name,
                    'code' => $line->lineOfBusiness->code,
                    'declared_gross_sales_cents' => $line->declared_gross_sales_cents,
                    'capital_investment_cents' => $line->capital_investment_cents,
                ])->all(),
            ],
            'summary' => [
                'office_count' => $routing->works->pluck('office_code')->unique()->count(),
                'responsibility_count' => $responsibilities->count(),
                'resolved_count' => $resolvedCount,
                'assessment_created' => $application->assessments()->exists(),
                'payment_order_count' => $application->paperlessPaymentOrders()->whereNull('superseded_at')->count(),
                'routine_default_count' => $routineDefaultCount,
                'manual_review_count' => $openResponsibilities->count() - $routineDefaultCount,
            ],
            'offices' => $offices->all(),
            'routing' => [
                'situational_context' => $routing->situational_context,
                'determined_by' => $routing->determinedBy->name,
                'determined_at' => $routing->determined_at->toIso8601String(),
            ],
            'audit' => [
                'routing_determination_id' => $routing->id,
                'evaluation_id' => $evaluation->id,
                'evaluation_version' => $projection['version_sequence'],
                'evaluation_fingerprint' => $projection['current_fingerprint'],
                'responsibility_profile_version' => $this->profileVersion($responsibilities),
                'classification' => 'Synthetic cleanroom · provisional only',
                'production_liability' => false,
            ],
        ];
    }

    /** @param Collection<int, array<string, mixed>> $responsibilities */
    private function profileVersion(Collection $responsibilities): ?string
    {
        $version = data_get($responsibilities->first(), 'metadata.responsibility_profile_version');

        return is_string($version) ? $version : null;
    }
}
