<?php

namespace App\Actions;

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
    ) {}

    /**
     * @param  list<array{line_of_business_id: int, items?: list<array{fee_rule_id: int, amount_cents: int, reason?: string|null, authority?: string|null}>}>  $selections
     * @return list<TreasuryLineOfBusinessAssignment>
     */
    public function handle(PermitApplication $permitApplication, array $selections, User $actor): array
    {
        return DB::transaction(function () use ($permitApplication, $selections, $actor): array {
            $application = PermitApplication::query()->with(['bploRoutingDetermination.works.paymentOrders'])->lockForUpdate()->findOrFail($permitApplication->id);
            if (! $actor->can(UserPermission::CorrectEvaluationLinesOfBusiness->value)) {
                throw new LogicException('Only an authorized Treasury actor may assign official Lines of Business.');
            }
            if ($application->type !== PermitApplicationType::New || data_get($application->metadata, 'nelson_reconciliation_v1.commissioned_path') !== true) {
                throw new LogicException('Treasury multi-LOB classification in this wave is commissioned only for Nelson-grounded New Applications.');
            }
            if ($selections === [] || collect($selections)->pluck('line_of_business_id')->duplicates()->isNotEmpty()) {
                throw new LogicException('Treasury must assign one or more unique canonical Lines of Business.');
            }
            $selectedFeeIds = collect($selections)->flatMap(fn (array $selection): array => collect($selection['items'] ?? [])->pluck('fee_rule_id')->all());
            if ($selectedFeeIds->duplicates()->isNotEmpty()) {
                throw new LogicException('Each Treasury payment item may be selected only once.');
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

                $configuredItems = $selection['items'] ?? $this->defaultItems($application, $line);
                if ($configuredItems === []) {
                    throw new LogicException('Each assigned Line of Business requires at least one confirmed Treasury payment item.');
                }
                foreach ($configuredItems as $item) {
                    $rule = FeeRule::query()->with(['currentReconciliation', 'lineOfBusinesses:id'])->findOrFail($item['fee_rule_id']);
                    if ($rule->line_of_business_id !== $line->id && ! $rule->lineOfBusinesses->contains('id', $line->id)) {
                        throw new LogicException('Treasury LOB payment items must belong to the selected canonical Line of Business.');
                    }
                    if ($rule->category === FeeRuleCategory::Tax) {
                        throw new LogicException('Business Tax is prohibited for New Applications.');
                    }
                    $amount = $item['amount_cents'];
                    if ($amount < 0
                        || ! $rule->is_active
                        || $rule->effective_from->year > $application->application_year
                        || ($rule->effective_until !== null && $rule->effective_until->year < $application->application_year)) {
                        throw new LogicException('The Treasury payment item must be active for the Application year and have a non-negative amount.');
                    }
                    $variance = $amount - $rule->amount_cents;
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
                        'default_amount_cents' => $rule->amount_cents,
                        'determined_amount_cents' => $amount,
                        'variance_cents' => $variance,
                        'currency' => 'PHP',
                        'determined_at' => now(),
                        'source_snapshot' => [
                            'financial_source' => 'treasury_lob_component',
                            'permit_application_line_id' => $applicationLine->id,
                            'fee_rule_id' => $rule->id,
                            'fee_rule_version' => $this->feeRuleVersion($rule),
                            'default_amount_minor' => $rule->amount_cents,
                            'determined_amount_minor' => $amount,
                            'variance_minor' => $variance,
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

    /** @return list<array{fee_rule_id: int, amount_cents: int}> */
    private function defaultItems(PermitApplication $application, LineOfBusiness $line): array
    {
        return array_values(FeeRule::query()
            ->where(fn ($query) => $query->where('line_of_business_id', $line->id)
                ->orWhereHas('lineOfBusinesses', fn ($query) => $query->whereKey($line->id)))
            ->where('is_active', true)
            ->where('effective_from', '<=', "{$application->application_year}-12-31")
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', "{$application->application_year}-01-01"))
            ->where('category', '!=', FeeRuleCategory::Tax->value)
            ->where('calculation_type', 'fixed')
            ->orderBy('code')->get()
            ->map(fn (FeeRule $rule): array => ['fee_rule_id' => $rule->id, 'amount_cents' => $rule->amount_cents])
            ->values()
            ->all());
    }

    private function feeRuleVersion(FeeRule $rule): string
    {
        $reconciliationVersion = $rule->currentReconciliation === null ? 'unreconciled' : $rule->currentReconciliation->version;

        return implode(':', ['fee_rule', $rule->id, $rule->effective_from->toDateString(), $rule->effective_until?->toDateString() ?? 'open', $reconciliationVersion]);
    }
}
