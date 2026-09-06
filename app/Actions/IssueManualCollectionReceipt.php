<?php

namespace App\Actions;

use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class IssueManualCollectionReceipt
{
    public function __construct(private readonly ResolveOfficialReceiptProfile $resolveProfile) {}

    /**
     * @param  array{receipt_number: string, numbering_authority: string, receipt_group_key?: string, series?: string|null, remarks?: string|null}  $data
     */
    public function handle(TreasuryCollection $collection, array $data, ?User $issuedBy = null): Receipt
    {
        return DB::transaction(function () use ($collection, $data, $issuedBy): Receipt {
            $collection = TreasuryCollection::query()
                ->whereKey($collection->id)
                ->with(['paymentSchedule', 'permitApplication.business.owner', 'assessment', 'receipts', 'allocations'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($collection->status, [TreasuryCollectionStatus::PendingReceipt, TreasuryCollectionStatus::Receipted], true)) {
                throw new LogicException("Receipt cannot be issued for collection [{$collection->id}] with status [{$collection->status->value}].");
            }

            if ($collection->allocations->isEmpty() && $collection->receipts->isNotEmpty()) {
                return $collection->receipts->first()->load(['issuedBy', 'treasuryCollection', 'allocations']);
            }

            $groups = $collection->allocations->groupBy(
                fn ($allocation): string => filled($allocation->receipt_group_key)
                    ? (string) $allocation->receipt_group_key
                    : 'municipal_consolidated',
            );
            $groupKey = $data['receipt_group_key'] ?? ($groups->count() === 1
                ? (string) $groups->keys()->first()
                : ($groups->isEmpty() ? 'municipal_consolidated' : null));
            if (! is_string($groupKey) || ($groups->isNotEmpty() && ! $groups->has($groupKey))) {
                throw new LogicException('Select one required receipt group before issuing its Official Receipt.');
            }

            $existing = $collection->receipts->firstWhere('receipt_group_key', $groupKey);
            if ($existing instanceof Receipt) {
                return $existing->load(['issuedBy', 'treasuryCollection', 'allocations']);
            }

            $allocations = $groups->get($groupKey, collect());
            if ($allocations->contains(fn ($allocation): bool => $allocation->receipt_id !== null)) {
                throw new LogicException('A receipt-group allocation is already bound to another Official Receipt.');
            }
            $groupLabel = $allocations->isNotEmpty() && filled($allocations->first()->receipt_group_label)
                ? (string) $allocations->first()->receipt_group_label
                : 'Municipal Consolidated Collection';
            $groupAmount = $allocations->isEmpty() ? $collection->amount_cents : (int) $allocations->sum('amount_cents');

            $receipt = Receipt::query()->create([
                'treasury_collection_id' => $collection->id,
                'receipt_group_key' => $groupKey,
                'receipt_group_label' => $groupLabel,
                'payment_schedule_id' => $collection->payment_schedule_id,
                'permit_application_id' => $collection->permit_application_id,
                'assessment_id' => $collection->assessment_id,
                'issued_by_id' => $issuedBy?->id,
                'status' => ReceiptStatus::Issued,
                'numbering_authority' => $data['numbering_authority'],
                'receipt_number' => $data['receipt_number'],
                'series' => $data['series'] ?? null,
                'amount_cents' => $groupAmount,
                'issued_at' => now(),
                'remarks' => $data['remarks'] ?? null,
                'source_snapshot' => $this->sourceSnapshot($collection, $data, $issuedBy, $groupKey, $groupLabel, $groupAmount),
            ]);

            if ($allocations->isNotEmpty()) {
                $collection->allocations()->whereIn('id', $allocations->pluck('id'))->update([
                    'receipt_id' => $receipt->id,
                    'receipt_group_key' => $groupKey,
                    'receipt_group_label' => $groupLabel,
                ]);
            }
            $collection->status = $collection->allocations()->whereNull('receipt_id')->exists()
                ? TreasuryCollectionStatus::PendingReceipt
                : TreasuryCollectionStatus::Receipted;
            $collection->save();

            return $receipt->load(['issuedBy', 'treasuryCollection', 'allocations']);
        });
    }

    /**
     * @param  array{receipt_number: string, numbering_authority: string, receipt_group_key?: string, series?: string|null, remarks?: string|null}  $data
     * @return array<string, mixed>
     */
    private function sourceSnapshot(TreasuryCollection $collection, array $data, ?User $issuedBy, string $groupKey, string $groupLabel, int $groupAmount): array
    {
        $profile = $this->resolveProfile->handle();

        return [
            'treasury_collection_id' => $collection->id,
            'collection_status_before' => $collection->status->value,
            'collection_channel' => $collection->channel->value,
            'collection_method' => $collection->method->value,
            'collection_amount_cents' => $collection->amount_cents,
            'receipt_group' => ['key' => $groupKey, 'label' => $groupLabel, 'amount_cents' => $groupAmount],
            'payment_schedule_id' => $collection->payment_schedule_id,
            'permit_application' => [
                'id' => $collection->permitApplication->id,
                'application_number' => $collection->permitApplication->application_number,
                'business_name' => $collection->permitApplication->business->name,
                'owner_name' => $collection->permitApplication->business->owner->name,
            ],
            'receipt' => [
                'numbering_authority' => $data['numbering_authority'],
                'receipt_number' => $data['receipt_number'],
            ],
            'official_receipt_profile' => $profile,
            'af51' => [
                'copy_designation' => data_get($profile, 'form.copy_designation'),
                'agency' => data_get($profile, 'defaults.agency'),
                'fund' => data_get($profile, 'defaults.fund'),
                'series' => $data['series'] ?? null,
                'amount_in_words' => $this->amountInWords($groupAmount),
            ],
            'issuer' => [
                'authenticated_user_id' => $issuedBy?->id,
                'authenticated_user_name' => $issuedBy?->name,
                'printed_name' => data_get($profile, 'collecting_officer.name'),
                'printed_title' => data_get($profile, 'collecting_officer.title'),
                'printed_designation' => data_get($profile, 'collecting_officer.designation'),
                'signature_applied' => false,
            ],
            'policy' => [
                'numbering_mode' => 'manual',
                'note' => 'Receipt number was supplied by the issuing user. Automatic receipt numbering authority and duplication policy remain explicit later decisions.',
            ],
        ];
    }

    private function amountInWords(int $amountCents): string
    {
        $pesos = intdiv($amountCents, 100);
        $centavos = $amountCents % 100;
        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);
            $pesoWords = $formatter->format($pesos);
            $centavoWords = $formatter->format($centavos);
            if (is_string($pesoWords) && is_string($centavoWords)) {
                return mb_strtoupper("{$pesoWords} PESOS AND {$centavoWords} CENTAVOS ONLY");
            }
        }

        return mb_strtoupper(number_format($amountCents / 100, 2).' PESOS ONLY');
    }
}
