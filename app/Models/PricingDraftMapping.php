<?php

namespace App\Models;

use Database\Factories\PricingDraftMappingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use LogicException;

/** Append-only mapping evidence, never fiscal acceptance or execution authority. */
class PricingDraftMapping extends Model
{
    /** @use HasFactory<PricingDraftMappingFactory> */
    use HasFactory;

    protected $fillable = [
        'pricing_definition_draft_id', 'revision', 'pricing_charge_item_id',
        'revenue_account_id', 'recorded_by', 'evidence_reference', 'evidence_sha256', 'rationale',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $mapping): void {
            $revision = $mapping->getAttributes()['revision'] ?? null;
            if (! is_int($revision) || $revision < 1) {
                throw new InvalidArgumentException('Mapping revision must be a positive integer.');
            }
            foreach (['evidence_reference', 'rationale'] as $field) {
                if (! is_string($mapping->getAttribute($field)) || trim($mapping->getAttribute($field)) === '') {
                    throw new InvalidArgumentException('Mapping evidence and rationale are required.');
                }
            }
            if (! is_string($mapping->getAttribute('evidence_sha256'))
                || ! preg_match('/^[a-f0-9]{64}$/D', $mapping->getAttribute('evidence_sha256'))) {
                throw new InvalidArgumentException('Mapping evidence requires a SHA-256 fingerprint.');
            }
            foreach (['pricing_definition_draft_id', 'pricing_charge_item_id', 'recorded_by', 'revenue_account_id'] as $field) {
                $identity = $mapping->getAttribute($field);
                if ($identity === null && $field === 'revenue_account_id') {
                    continue;
                }
                if (! is_int($identity) || $identity < 1) {
                    throw new InvalidArgumentException('Mapping references must be explicit positive integer identities.');
                }
            }
            $draft = PricingDefinitionDraft::query()->where('id', $mapping->getAttribute('pricing_definition_draft_id'))->firstOrFail();
            $item = PricingChargeItem::query()->where('id', $mapping->getAttribute('pricing_charge_item_id'))->firstOrFail();
            $accountId = $mapping->getAttribute('revenue_account_id');
            $account = $accountId === null ? null : RevenueAccount::query()->where('id', $accountId)->firstOrFail();
            User::query()->where('id', $mapping->getAttribute('recorded_by'))->firstOrFail();
            $mapping->setAttribute('identity_snapshot', [
                'schema_version' => 1,
                'classification' => 'mapping_evidence_only',
                'executable' => false,
                'draft_code' => $draft->code,
                'draft_revision' => $draft->revision,
                'source_sha256' => $draft->source_sha256,
                'source_locator' => $draft->source_locator,
                'source_account_code' => $draft->revenue_account_code,
                'charge_code' => $item->getAttribute('code'),
                'charge_name' => $item->getAttribute('name'),
                'account_code' => $account?->code,
                'account_name' => $account?->name,
            ]);
        });
        static::updating(function (): never {
            throw new LogicException('Mapping evidence is append-only; record a new revision.');
        });
        static::deleting(function (): never {
            throw new LogicException('Mapping evidence must be retained.');
        });
    }

    public function assertExecutable(): never
    {
        throw new LogicException('Mapping evidence does not authorize pricing execution.');
    }

    /** @return BelongsTo<PricingDefinitionDraft, $this> */
    public function draft(): BelongsTo
    {
        return $this->belongsTo(PricingDefinitionDraft::class, 'pricing_definition_draft_id');
    }

    /** @return BelongsTo<PricingChargeItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(PricingChargeItem::class, 'pricing_charge_item_id');
    }

    /** @return BelongsTo<RevenueAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(RevenueAccount::class, 'revenue_account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected function casts(): array
    {
        return ['revision' => 'integer', 'identity_snapshot' => 'array'];
    }
}
