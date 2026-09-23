<?php

namespace App\Models;

use App\Assessment\PricingDefinitionDraft as Definition;
use Database\Factories\PricingDefinitionDraftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @property string $code
 * @property int $revision
 * @property string $method
 * @property string $basis
 * @property string|null $unit_code
 * @property int|null $amount_minor
 * @property string $currency
 * @property string|null $revenue_account_code
 * @property string $source_sha256
 * @property string $source_locator
 * @property array<string, mixed> $source_evidence
 */
class PricingDefinitionDraft extends Model
{
    /** @use HasFactory<PricingDefinitionDraftFactory> */
    use HasFactory;

    protected $fillable = [
        'code', 'revision', 'method', 'basis', 'unit_code', 'amount_minor', 'currency',
        'revenue_account_code', 'source_sha256', 'source_locator', 'source_evidence',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $draft): void {
            // Validate before integer casts can truncate a decimal or coerce a numeric string.
            $raw = $draft->getAttributes();
            if (! is_int($raw['revision'] ?? null)
                || (isset($raw['amount_minor']) && ! is_int($raw['amount_minor']))) {
                throw new \InvalidArgumentException('Draft revisions and minor amounts require exact integers.');
            }
            $draft->definition();
        });
        static::updating(function (): never {
            throw new LogicException('Recorded drafts are append-only; create a new revision.');
        });
        static::deleting(function (): never {
            throw new LogicException('Recorded draft evidence must be retained.');
        });
    }

    public function definition(): Definition
    {
        return new Definition(
            $this->code, $this->revision, $this->method, $this->basis, $this->unit_code,
            $this->amount_minor, $this->currency, $this->revenue_account_code,
            $this->source_sha256, $this->source_locator, $this->source_evidence,
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['revision' => 'integer', 'amount_minor' => 'integer', 'source_evidence' => 'array'];
    }
}
