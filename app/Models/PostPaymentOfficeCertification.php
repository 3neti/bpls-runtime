<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $permit_application_id
 * @property int $bplo_routing_determination_id
 * @property int $receipt_id
 * @property int|null $certified_by_id
 * @property string $office_code
 * @property string $office_label
 * @property list<int> $routing_work_ids
 * @property string $status
 * @property string|null $result
 * @property string|null $remarks
 * @property array<string, mixed> $evidence
 * @property Carbon|null $certified_at
 * @property string $semantic_classification
 * @property bool $production_authority
 * @property-read PermitApplication $permitApplication
 * @property-read BploRoutingDetermination $routingDetermination
 * @property-read Receipt $receipt
 * @property-read User|null $certifiedBy
 */
class PostPaymentOfficeCertification extends Model
{
    protected $fillable = [
        'permit_application_id',
        'bplo_routing_determination_id',
        'receipt_id',
        'certified_by_id',
        'office_code',
        'office_label',
        'routing_work_ids',
        'status',
        'result',
        'remarks',
        'evidence',
        'certified_at',
        'semantic_classification',
        'production_authority',
    ];

    /** @return BelongsTo<PermitApplication, $this> */
    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    /** @return BelongsTo<BploRoutingDetermination, $this> */
    public function routingDetermination(): BelongsTo
    {
        return $this->belongsTo(BploRoutingDetermination::class, 'bplo_routing_determination_id');
    }

    /** @return BelongsTo<Receipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /** @return BelongsTo<User, $this> */
    public function certifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certified_by_id');
    }

    protected function casts(): array
    {
        return [
            'routing_work_ids' => 'array',
            'evidence' => 'array',
            'certified_at' => 'datetime',
            'production_authority' => 'boolean',
        ];
    }
}
