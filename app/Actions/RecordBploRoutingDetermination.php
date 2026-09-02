<?php

namespace App\Actions;

use App\Enums\UserPermission;
use App\Models\BploRoutingDetermination;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class RecordBploRoutingDetermination
{
    /**
     * @param  list<array{office_code: string, office_label: string, situational_reason: string, required_work: string, permit_application_line_id?: int|null}>  $selectedWork
     */
    public function handle(
        PermitApplication $permitApplication,
        User $bploActor,
        string $situationalContext,
        array $selectedWork,
    ): BploRoutingDetermination {
        return DB::transaction(function () use ($permitApplication, $bploActor, $situationalContext, $selectedWork): BploRoutingDetermination {
            $application = PermitApplication::query()->whereKey($permitApplication->id)->lockForUpdate()->firstOrFail();
            $application->load(['lines.lineOfBusiness', 'bploRoutingDetermination']);

            if (! $bploActor->can(UserPermission::DetermineBploRouting->value)) {
                throw new LogicException('Only an authorized BPLO actor may determine concerned-office routing.');
            }

            if ($application->submitted_at === null) {
                throw new LogicException('BPLO routing begins only after the Application is lodged.');
            }

            if ($application->bploRoutingDetermination instanceof BploRoutingDetermination) {
                return $application->bploRoutingDetermination->load(['determinedBy', 'works.lineOfBusiness']);
            }

            if ($selectedWork === []) {
                throw new LogicException('BPLO must record at least one selected concerned office and its required work.');
            }

            if (blank($situationalContext)) {
                throw new LogicException('BPLO must record the situational context for its routing determination.');
            }

            $availableLines = $application->lines->keyBy('id');
            $normalizedWork = collect($selectedWork)->map(function (array $work) use ($application, $availableLines): array {
                $applicationLineId = Arr::get($work, 'permit_application_line_id');
                $applicationLine = $applicationLineId === null ? null : $availableLines->get($applicationLineId);

                if ($applicationLineId !== null && ! $applicationLine instanceof PermitApplicationLine) {
                    throw new LogicException('BPLO routing may reference only a Line of Business declared on this Application.');
                }

                $officeCode = Str::of($work['office_code'])->trim()->lower()->replaceMatches('/[^a-z0-9_-]+/', '-')->trim('-')->toString();
                if ($officeCode === '' || blank($work['office_label']) || blank($work['situational_reason']) || blank($work['required_work'])) {
                    throw new LogicException('Each BPLO route requires an office, situational reason, and required work.');
                }

                return [
                    'office_code' => $officeCode,
                    'office_label' => Str::squish($work['office_label']),
                    'situational_reason' => Str::squish($work['situational_reason']),
                    'required_work' => Str::squish($work['required_work']),
                    'permit_application_line_id' => $applicationLine?->id,
                    'line_of_business_id' => $applicationLine?->line_of_business_id,
                    'context_snapshot' => [
                        'application_type' => $application->type->value,
                        'application_year' => $application->application_year,
                        'permit_application_line_id' => $applicationLine?->id,
                        'line_of_business_id' => $applicationLine?->line_of_business_id,
                        'line_of_business_code' => $applicationLine?->lineOfBusiness?->code,
                        'line_of_business_name' => $applicationLine?->lineOfBusiness?->name,
                        'selection_authority' => 'bplo_situational_determination',
                        'automatic_lob_rule' => false,
                    ],
                ];
            })->values();

            if ($normalizedWork->duplicates(fn (array $work): string => $work['office_code'].'|'.($work['permit_application_line_id'] ?? 'application'))->isNotEmpty()) {
                throw new LogicException('The same office and Application/LOB context may be selected only once.');
            }

            $determination = $application->bploRoutingDetermination()->create([
                'determined_by_id' => $bploActor->id,
                'situational_context' => Str::squish($situationalContext),
                'application_facts_snapshot' => [
                    'permit_application_id' => $application->id,
                    'type' => $application->type->value,
                    'application_year' => $application->application_year,
                    'submitted_at' => $application->submitted_at->toIso8601String(),
                    'applicant_declaration_preserved' => true,
                    'declared_lines' => $application->lines->map(fn (PermitApplicationLine $line): array => [
                        'permit_application_line_id' => $line->id,
                        'line_of_business_id' => $line->line_of_business_id,
                        'code' => $line->lineOfBusiness?->code,
                        'name' => $line->lineOfBusiness?->name,
                        'declared_gross_sales_cents' => $line->declared_gross_sales_cents,
                        'capital_investment_cents' => $line->capital_investment_cents,
                    ])->all(),
                ],
                'determined_at' => now(),
            ]);

            $determination->works()->createMany($normalizedWork->all());

            return $determination->load(['determinedBy', 'works.lineOfBusiness']);
        });
    }
}
