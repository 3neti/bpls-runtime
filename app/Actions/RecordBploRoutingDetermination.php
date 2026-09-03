<?php

namespace App\Actions;

use App\Enums\UserPermission;
use App\Models\BploRoutingDetermination;
use App\Models\BploRoutingSuggestion;
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
        return $this->record(
            $permitApplication,
            $bploActor,
            $situationalContext,
            $selectedWork,
            BploRoutingSuggestion::BploConfirmed,
        );
    }

    public function handleSystemDefault(BploRoutingSuggestion $suggestion, User $bploServiceActor): BploRoutingDetermination
    {
        if ($suggestion->status !== BploRoutingSuggestion::AwaitingConfirmation
            || $suggestion->review_due_at->isFuture()) {
            throw new LogicException('The BPLO routing suggestion is not eligible for a system default.');
        }

        return $this->record(
            $suggestion->permitApplication,
            $bploServiceActor,
            $suggestion->situational_context,
            $this->systemDefaultWork($suggestion),
            BploRoutingSuggestion::SystemDefaulted,
            $suggestion->id,
        );
    }

    /**
     * @param  list<array{office_code: string, office_label: string, situational_reason: string, required_work: string, permit_application_line_id?: int|null}>  $selectedWork
     */
    private function record(
        PermitApplication $permitApplication,
        User $bploActor,
        string $situationalContext,
        array $selectedWork,
        string $origin,
        ?int $expectedSuggestionId = null,
    ): BploRoutingDetermination {
        return DB::transaction(function () use ($permitApplication, $bploActor, $situationalContext, $selectedWork, $origin, $expectedSuggestionId): BploRoutingDetermination {
            $application = PermitApplication::query()->whereKey($permitApplication->id)->lockForUpdate()->firstOrFail();
            $application->load(['lines.lineOfBusiness', 'bploRoutingDetermination', 'bploRoutingSuggestion']);

            if (! $bploActor->can(UserPermission::DetermineBploRouting->value)) {
                throw new LogicException('Only an authorized BPLO actor may determine concerned-office routing.');
            }

            if ($application->submitted_at === null) {
                throw new LogicException('BPLO routing begins only after the Application is lodged.');
            }

            if ($application->bploRoutingDetermination instanceof BploRoutingDetermination) {
                $this->resolveSuggestion($application->bploRoutingSuggestion, $application->bploRoutingDetermination, $origin);

                return $application->bploRoutingDetermination->load(['determinedBy', 'works.lineOfBusiness']);
            }

            $suggestion = $application->bploRoutingSuggestion;
            if ($expectedSuggestionId !== null
                && (! $suggestion instanceof BploRoutingSuggestion
                    || $suggestion->id !== $expectedSuggestionId
                    || $suggestion->status !== BploRoutingSuggestion::AwaitingConfirmation
                    || $suggestion->review_due_at->isFuture())) {
                throw new LogicException('The BPLO routing suggestion changed before the sentinel could act.');
            }

            if ($selectedWork === []) {
                throw new LogicException('BPLO must record at least one selected concerned office and its required work.');
            }

            if (blank($situationalContext)) {
                throw new LogicException('BPLO must record the situational context for its routing determination.');
            }

            $availableLines = $application->lines->keyBy('id');
            $normalizedWork = collect($selectedWork)->map(function (array $work) use ($application, $availableLines, $origin): array {
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
                        'selection_authority' => $origin === BploRoutingSuggestion::SystemDefaulted
                            ? 'system_defaulted_bplo_routing_profile'
                            : 'bplo_situational_determination',
                        'automatic_lob_rule' => false,
                        'routing_origin' => $origin,
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
                    'routing_origin' => $origin,
                    'routing_suggestion_id' => $suggestion?->id,
                    'routing_profile_version' => $suggestion?->profile_version,
                    'routing_review_due_at' => $suggestion?->review_due_at?->toIso8601String(),
                    'silence_is_office_approval' => false,
                    'silence_creates_financial_authority' => false,
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
            $this->resolveSuggestion($suggestion, $determination, $origin);

            return $determination->load(['determinedBy', 'works.lineOfBusiness']);
        });
    }

    private function resolveSuggestion(
        ?BploRoutingSuggestion $suggestion,
        BploRoutingDetermination $determination,
        string $origin,
    ): void {
        if (! $suggestion instanceof BploRoutingSuggestion
            || $suggestion->status !== BploRoutingSuggestion::AwaitingConfirmation) {
            return;
        }

        $suggestion->update([
            'routing_determination_id' => $determination->id,
            'status' => $origin,
            'resolved_at' => $determination->determined_at,
        ]);
    }

    /**
     * @return list<array{office_code: string, office_label: string, situational_reason: string, required_work: string, permit_application_line_id: int|null}>
     */
    private function systemDefaultWork(BploRoutingSuggestion $suggestion): array
    {
        $selectedWork = [];

        foreach ($suggestion->suggested_work as $work) {
            $officeCode = Arr::get($work, 'office_code');
            $officeLabel = Arr::get($work, 'office_label');
            $situationalReason = Arr::get($work, 'situational_reason');
            $requiredWork = Arr::get($work, 'required_work');
            $applicationLineId = Arr::get($work, 'permit_application_line_id');

            if (! is_string($officeCode)
                || ! is_string($officeLabel)
                || ! is_string($situationalReason)
                || ! is_string($requiredWork)
                || (! is_int($applicationLineId) && $applicationLineId !== null)) {
                throw new LogicException('The persisted BPLO routing suggestion work is invalid.');
            }

            $selectedWork[] = [
                'office_code' => $officeCode,
                'office_label' => $officeLabel,
                'situational_reason' => $situationalReason,
                'required_work' => $requiredWork,
                'permit_application_line_id' => $applicationLineId,
            ];
        }

        return $selectedWork;
    }
}
