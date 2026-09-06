<?php

namespace App\Models;

use Database\Factories\SignatureEvidenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $signer_id
 * @property string $signable_type
 * @property int $signable_id
 * @property string $purpose
 * @property string $method
 * @property string $evidence_digest
 * @property array<string, mixed> $source_snapshot
 * @property Carbon $captured_at
 * @property-read User $signer
 * @property-read Model $signable
 */
#[Fillable(['signer_id', 'signable_type', 'signable_id', 'purpose', 'method', 'evidence_digest', 'source_snapshot', 'captured_at'])]
class SignatureEvidence extends Model implements HasMedia
{
    /** @use HasFactory<SignatureEvidenceFactory> */
    use HasFactory, InteractsWithMedia;

    public const FacsimileCollection = 'signature_facsimile';

    protected $table = 'signature_evidences';

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Signature evidence is immutable.'));
        static::deleting(fn (): never => throw new LogicException('Signature evidence cannot be deleted.'));
    }

    /** @return BelongsTo<User, $this> */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_id');
    }

    /** @return MorphTo<Model, $this> */
    public function signable(): MorphTo
    {
        return $this->morphTo();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::FacsimileCollection)->singleFile()->useDisk('local');
    }

    protected function casts(): array
    {
        return ['source_snapshot' => 'array', 'captured_at' => 'datetime'];
    }
}
