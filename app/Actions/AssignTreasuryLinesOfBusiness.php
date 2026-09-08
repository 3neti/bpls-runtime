<?php

namespace App\Actions;

use App\Assessment\AssessmentCalculator;
use App\Enums\FeeDeterminationChannel;
use App\Enums\FeeRuleCategory;
use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\TreasuryLineOfBusinessAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class AssignTreasuryLinesOfBusiness
{
    public function __construct(
        private readonly BuildConcernedOfficePaymentOrderSummary $paymentOrderSummary,
        private readonly RefreshBusinessPermitEvaluation $refreshEvaluation,
        private readonly AssessmentCalculator $assessmentCalculator,
    ) {}

    /**
     * @param  list<array{line_of_business_id: int, items?: list<array{fee_rule_id: int, amount_cents: int, reason?: string|null, authority?: string|null}>}>  $selections
     * @return list<TreasuryLineOfBusinessAssignment>
     */
    public function handle(PermitApplication $permitApplication, array $selections, User $actor): array
    {
        return DB::transaction(function () use ($permitApplication, $selections, $actor): array {
            $application = PermitApplication::query()->with(['bploRoutingDetermination.works.paymentOrders.lines'])->lockForUpdate()->findOrFail($permitApplication->id);
            if (! $actor->can(UserPermission::CorrectEvaluationLinesOfBusiness->value)) {
                throw new LogicException('Only an authorized Treasury actor may assign official Lines of Business.');
            }
            if ($application->type !== PermitApplicationType::New || data_get($application->metadata, 'nelson_reconciliation_v1.commissioned_path') !== true) {
                throw new LogicException('Treasury multi-LOB classification in this wave is commissioned only for Nelson-grounded New Applications.');
            }
            if ($selections === [] || collect($selections)->pluck('line_of_business_id')->duplicates()->isNotEmpty()) {
                throw new LogicException('Treasury must assign one or more unique canonical Lines of Business.');
            }
            $selections = $this->normalizeApplicationWideItems($selections);
            $selectedFeeIds = collect($selections)->flatMap(fn (array $selection): array => collect($selection['items'])->pluck('fee_rule_id')->all());
            if ($selectedFeeIds->isEmpty()) {
                throw new LogicException('Treasury classification requires at least one confirmed payment item.');
            }
            if ($selectedFeeIds->duplicates()->isNotEmpty()) {
                throw new LogicException('Each Treasury payment item may be selected only once.');
            }
            $routingWorks = $application->bploRoutingDetermination->works ?? collect();
            $paymentOrderFeeIds = $routingWorks
                ->flatMap->paymentOrders
                ->whereNull('superseded_at')
                ->flatMap->lines
                ->map(fn ($line): mixed => data_get($line->source_snapshot, 'fee_rule_id'))
                ->filter()
                ->map(fn (mixed $id): int => (int) $id);
            if ($selectedFeeIds->intersect($paymentOrderFeeIds)->isNotEmpty()) {
                throw new LogicException('A fee already determined by a concerned-office Payment Order cannot be added by Treasury.');
            }
            $paymentOrderExactOnceKeys = $routingWorks
                ->flatMap->paymentOrders->whereNull('superseded_at')->flatMap->lines
                ->map(fn ($line): mixed => data_get($line->source_snapshot, 'exact_once_key'))->filter();
            $selectedExactOnceKeys = FeeRule::query()->whereIn('id', $selectedFeeIds)->get()
                ->map(fn (FeeRule $rule): mixed => data_get($rule->metadata, 'exact_once_key'))->filter();
            if ($selectedExactOnceKeys->intersect($paymentOrderExactOnceKeys)->isNotEmpty()) {
                throw new LogicException('An application-wide fee already determined by a concerned office cannot be added by Treasury.');
            }
            $paymentOrders = $this->paymentOrderSummary->handle($application);
            if ($paymentOrders['all_finalized'] !== true) {
                throw new LogicException('Treasury classification begins only after every routed office has exactly one current, reconciled Payment Order.');
            }
            if ($application->treasuryLineOfBusinessAssignments()->whereNull('removed_at')->exists()) {
                throw new LogicException('Treasury classification has already been confirmed for this Application.');
            }

            $lineIds = collect($selections)->pluck('line_of_business_id');
            $lines = LineOfBusiness::query()->whereIn('id', $lineIds)->where('is_active', true)->get()->keyBy('id');
            if ($lines->count() !== count($selections)) {
                throw new LogicException('Treasury may assign only active canonical Lines of Business.');
            }

            $result = [];
            foreach ($selections as $index => $selection) {
                $line = $lines->get($selection['line_of_business_id']);
                $assignment = $application->treasuryLineOfBusinessAssignments()->create([
                    'line_of_business_id' => $line->id,
                    'assigned_by_id' => $actor->id,
                    'sequence' => $index + 1,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                    'source_snapshot' => [
                        'classification_source' => 'treasury_municipal_truth',
                        'applicant_description_preserved' => $application->business_activity_description,
                        'applicant_declaration_rewritten' => false,
                        'line_of_business_id' => $line->id,
                        'line_of_business_code' => $line->code,
                    ],
                ]);
                $applicationLine = $application->lines()->create([
                    'line_of_business_id' => $line->id,
                    'declared_gross_sales_cents' => 0,
                    'capital_investment_cents' => 0,
                    'quantity' => 1,
                    'metadata' => ['origin' => 'treasury_municipal_truth', 'treasury_assignment_id' => $assignment->id],
                ]);

                $configuredItems = $selection['items'];
                foreach ($configuredItems as $item) {
                    $feeRuleId = $item['fee_rule_id'] ?? null;
                    if (! is_int($feeRuleId)) {
                        throw new LogicException('A Treasury payment item requires a canonical fee rule.');
                    }
                    $rule = FeeRule::query()->with(['currentReconciliation', 'lineOfBusinesses:id'])->findOrFail($feeRuleId);
                    if ($rule->line_of_business_id !== $line->id && ! $rule->lineOfBusinesses->contains('id', $line->id)) {
                        throw new LogicException('Treasury LOB payment items must belong to the selected canonical Line of Business.');
                    }
                    if ($rule->category === FeeRuleCategory::Tax) {
                        throw new LogicException('Business Tax is prohibited for New Applications.');
                    }
                    if ($rule->determination_channel !== FeeDeterminationChannel::TreasuryLineOfBusiness) {
                        throw new LogicException('Treasury may add only fees owned by the Treasury Line of Business channel.');
                    }
                    $amount = $item['amount_cents'];
                    if ($amount < 0
                        || ! $rule->is_active
                        || $rule->effective_from->year > $application->application_year
                        || ($rule->effective_until !== null && $rule->effective_until->year < $application->application_year)) {
                        throw new LogicException('The Treasury payment item must be active for the Application year and have a non-negative amount.');
                    }
                    $calculation = data_get($rule->metadata, 'manual_amount_required') === true
                        ? ['basis_amount_cents' => 0, 'amount_cents' => $rule->amount_cents, 'range_id' => null, 'rule_snapshot' => null]
                        : $this->assessmentCalculator->calculate($rule, null, $application);
                    $defaultAmount = $calculation['amount_cents'];
                    $variance = $amount - $defaultAmount;
                    $reason = $item['reason'] ?? null;
                    $authority = $item['authority'] ?? null;
                    if ($variance !== 0) {
                        $reason ??= 'Amount edited in the Nelson financial line-item editor.';
                        $authority ??= 'Authorized Treasury actor holding '.UserPermission::CorrectEvaluationLinesOfBusiness->value.'.';
                    }
                    if ($variance !== 0 && (blank($reason) || blank($authority))) {
                        throw new LogicException('A Treasury item variance requires reason and authority provenance.');
                    }
                    $assignment->items()->create([
                        'fee_rule_id' => $rule->id,
                        'determined_by_id' => $actor->id,
                        'code' => $rule->code,
                        'name' => $rule->name,
                        'default_amount_cents' => $defaultAmount,
                        'determined_amount_cents' => $amount,
                        'variance_cents' => $variance,
                        'currency' => 'PHP',
                        'determined_at' => now(),
                        'source_snapshot' => [
                            'financial_source' => 'treasury_lob_component',
                            'permit_application_line_id' => $applicationLine->id,
                            'fee_rule_id' => $rule->id,
                            'fee_rule_version' => $this->feeRuleVersion($rule),
                            'scope' => $rule->scope->value,
                            'exact_once_key' => data_get($rule->metadata, 'exact_once_key', 'fee-rule-'.$rule->id),
                            'default_amount_minor' => $defaultAmount,
                            'determined_amount_minor' => $amount,
                            'variance_minor' => $variance,
                            'calculation' => $calculation,
                            'reason' => $reason,
                            'authority' => $authority,
                            'determined_by_id' => $actor->id,
                        ],
                    ]);
                }
                $result[] = $assignment->load(['lineOfBusiness', 'items.feeRule']);
            }
            if ($application->businessPermitEvaluation !== null) {
                $this->refreshEvaluation->handle($application->businessPermitEvaluation, $actor);
            }

            return $result;
        });
    }

    /**
     * @param  list<array{line_of_business_id: int, items?: list<array<string, mixed>>}>  $selections
     * @return list<array{line_of_business_id: int, items: list<array<string, mixed>>}>
     */
    private function normalizeApplicationWideItems(array $selections): array
    {
        $feeIds = collect($selections)->flatMap(fn (array $selection): array => collect($selection['items'] ?? [])->pluck('fee_rule_id')->all());
        $rules = FeeRule::query()->with('ranges')->whereIn('id', $feeIds)->get()->keyBy('id');
        $seen = [];

        foreach ($selections as &$selection) {
            $normalizedItems = [];
            foreach ($selection['items'] ?? [] as $item) {
                $rule = $rules->get($item['fee_rule_id']);
                if (! $rule instanceof FeeRule || $rule->scope->value !== 'application') {
                    $normalizedItems[] = $item;

                    continue;
                }

                $key = (string) data_get($rule->metadata, 'exact_once_key', 'fee-rule-'.$rule->id);
                $signature = $this->applicationWideRuleSignature($rule);
                if (isset($seen[$key])) {
                    if ($seen[$key] !== $signature) {
                        throw new LogicException("Conflicting application-wide fee rules require explicit municipal resolution [{$key}].");
                    }

                    continue;
                }

                $seen[$key] = $signature;
                $normalizedItems[] = $item;
            }
            $selection['items'] = $normalizedItems;
        }
        unset($selection);

        return $selections;
    }

    private function applicationWideRuleSignature(FeeRule $rule): string
    {
        return hash('sha256', json_encode([
            'name' => $rule->name,
            'calculation_type' => $rule->calculation_type->value,
            'basis' => $rule->basis,
            'basis_unit' => data_get($rule->metadata, 'basis_unit'),
            'unit_amount_minor' => data_get($rule->metadata, 'unit_amount_minor'),
            'amount_cents' => $rule->amount_cents,
            'ranges' => $rule->ranges->map->only(['min_basis_cents', 'max_basis_cents', 'amount_cents', 'rate_basis_points'])->values()->all(),
        ], JSON_THROW_ON_ERROR));
    }

    private function feeRuleVersion(FeeRule $rule): string
    {
        $reconciliationVersion = $rule->currentReconciliation === null ? 'unreconciled' : $rule->currentReconciliation->version;

        return implode(':', ['fee_rule', $rule->id, $rule->effective_from->toDateString(), $rule->effective_until?->toDateString() ?? 'open', $reconciliationVersion]);
    }
}
