<?php

namespace App\Actions;

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\UserPermission;
use App\Models\BploRoutingWork;
use App\Models\FeeRule;
use App\Models\PaperlessPaymentOrder;
use App\Models\User;
use App\References\ConcernedOfficeReference;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use LogicException;

class ConfirmOfficePaymentOrder
{
    public function __construct(
        private readonly CaptureSignatureEvidence $captureSignatureEvidence,
        private readonly ConcernedOfficeReference $concernedOffices,
    ) {}

    /**
     * @param  list<array{fee_rule_id: int, amount_cents: int, reason?: string|null, authority?: string|null}>  $items
     */
    public function handle(BploRoutingWork $work, array $items, User $actor, UploadedFile $signatureFacsimile): PaperlessPaymentOrder
    {
        return DB::transaction(function () use ($work, $items, $actor, $signatureFacsimile): PaperlessPaymentOrder {
            $work = BploRoutingWork::query()->with('determination.permitApplication')->lockForUpdate()->findOrFail($work->id);
            $application = $work->determination->permitApplication;
            if (! $actor->can(UserPermission::ContributeBusinessPermitEvaluations->value)) {
                throw new LogicException('Only an authorized concerned-office actor may confirm a Payment Order.');
            }
            if ($items === []) {
                throw new LogicException('A Payment Order requires at least one selected fee item.');
            }
            if ($application->assessments()->whereNull('superseded_at')->whereHas('decision', fn ($query) => $query->where('action', '!=', 'returned_for_correction'))->exists()) {
                throw new LogicException('A confirmed Assessment must be returned before a new Payment Order can be issued.');
            }

            $rules = FeeRule::query()->with('currentReconciliation')->whereIn('id', collect($items)->pluck('fee_rule_id'))->get()->keyBy('id');
            if ($rules->count() !== count($items)) {
                throw new LogicException('Every Payment Order item must reference the Municipal Schedule of Fees.');
            }

            $issuedAt = now();
            $order = $application->paperlessPaymentOrders()->create([
                'bplo_routing_work_id' => $work->id,
                'business_permit_evaluation_item_revision_id' => null,
                'issued_by_id' => $actor->id,
                'sequence' => ((int) $work->paymentOrders()->max('sequence')) + 1,
                'status' => 'issued',
                'total_amount_cents' => collect($items)->sum('amount_cents'),
                'issued_at' => $issuedAt,
                'source_snapshot' => [
                    'financial_source' => 'concerned_office_payment_order',
                    'office_code' => $work->office_code,
                    'office_label' => $work->office_label,
                    'bplo_routing_work_id' => $work->id,
                    'determined_by_id' => $actor->id,
                    'determined_at' => $issuedAt->toIso8601String(),
                    'editor_grammar' => 'fee_dropdown_default_edit_add.v1',
                ],
            ]);

            foreach ($items as $item) {
                $rule = $rules->get($item['fee_rule_id']);
                if (! $rule instanceof FeeRule || $rule->calculation_type !== FeeRuleCalculationType::Fixed) {
                    throw new LogicException('The simple Payment Order editor accepts only catalogued fixed-amount fees in this wave.');
                }
                if (! $rule->is_active
                    || $rule->effective_from->year > $application->application_year
                    || ($rule->effective_until !== null && $rule->effective_until->year < $application->application_year)) {
                    throw new LogicException('The selected fee is not active for this application year.');
                }
                if ($rule->category === FeeRuleCategory::Tax) {
                    throw new LogicException('Concerned-office Payment Orders cannot determine Business Tax.');
                }
                $configuredOffice = data_get($rule->metadata, 'responsible_office_code');
                if (is_string($configuredOffice) && $configuredOffice !== $work->office_code) {
                    throw new LogicException('The selected fee does not belong to this concerned office.');
                }
                if (data_get($application->metadata, 'nelson_reconciliation_v1.commissioned_path') === true) {
                    $office = collect($this->concernedOffices->items())->firstWhere('code', $work->office_code);
                    if (! is_array($office) || ! in_array($rule->code, $office['fee_rule_codes'] ?? [], true)) {
                        throw new LogicException('The selected fee is not in this concerned office’s configured Nelson fee menu.');
                    }

                    $semanticIdentity = str($rule->code.' '.$rule->name)
                        ->lower()
                        ->replace(['-', '_'], ' ')
                        ->squish()
                        ->toString();
                    if (str_contains($semanticIdentity, 'business tax')) {
                        throw new LogicException('Business Tax is prohibited for a Nelson New Application.');
                    }
                    if (str_contains($semanticIdentity, 'inspection')) {
                        throw new LogicException('Inspection is outside the Nelson V1 Payment Order path.');
                    }
                }
                $amount = $item['amount_cents'];
                if ($amount < 0) {
                    throw new LogicException('A Payment Order amount cannot be negative.');
                }
                $variance = $amount - $rule->amount_cents;
                if ($variance !== 0 && (blank($item['reason'] ?? null) || blank($item['authority'] ?? null))) {
                    throw new LogicException('An amount variance requires the existing reason and authority provenance.');
                }

                $order->lines()->create([
                    'code' => $rule->code,
                    'name' => $rule->name,
                    'amount_cents' => $amount,
                    'source_snapshot' => [
                        'scope' => 'application',
                        'fee_rule_id' => $rule->id,
                        'fee_rule_version' => $this->feeRuleVersion($rule),
                        'office_code' => $work->office_code,
                        'office_label' => $work->office_label,
                        'default_amount_minor' => $rule->amount_cents,
                        'determined_amount_minor' => $amount,
                        'variance_minor' => $variance,
                        'reason' => $item['reason'] ?? null,
                        'authority' => $item['authority'] ?? null,
                        'determined_by_id' => $actor->id,
                        'determined_at' => $issuedAt->toIso8601String(),
                    ],
                ]);
            }

            if ((int) $order->lines()->sum('amount_cents') !== $order->total_amount_cents) {
                throw new LogicException('Payment Order subtotal must equal its financial lines.');
            }

            $this->captureSignatureEvidence->handle($order, $actor, 'concerned_office_payment_order_confirmation', $signatureFacsimile);

            return $order->load(['lines', 'issuedBy']);
        });
    }

    private function feeRuleVersion(FeeRule $rule): string
    {
        $reconciliationVersion = $rule->currentReconciliation === null ? 'unreconciled' : $rule->currentReconciliation->version;

        return implode(':', ['fee_rule', $rule->id, $rule->effective_from->toDateString(), $rule->effective_until?->toDateString() ?? 'open', $reconciliationVersion]);
    }
}
