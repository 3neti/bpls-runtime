<?php

namespace App\Models;

use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_owner_id
 * @property string $name
 * @property string|null $trade_name
 * @property string|null $registration_number
 * @property string|null $address
 * @property string|null $barangay
 * @property string|null $ownership_type
 * @property string|null $organization_name
 * @property string|null $occupancy
 * @property string|null $building_name
 * @property string|null $property_index_number
 * @property string|null $business_area_square_meters
 * @property int|null $male_employee_count
 * @property int|null $female_employee_count
 * @property string|null $contact_number
 * @property string|null $email
 * @property Carbon|null $established_on
 * @property Carbon|null $started_on
 * @property Carbon|null $registered_on
 * @property string|null $legacy_source_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['business_owner_id', 'name', 'trade_name', 'registration_number', 'address', 'barangay', 'ownership_type', 'organization_name', 'occupancy', 'building_name', 'property_index_number', 'business_area_square_meters', 'male_employee_count', 'female_employee_count', 'contact_number', 'email', 'established_on', 'started_on', 'registered_on', 'legacy_source_id', 'metadata'])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    public function owner(): BelongsTo
    {
        return $this->belongsTo(BusinessOwner::class, 'business_owner_id');
    }

    public function permitApplications(): HasMany
    {
        return $this->hasMany(PermitApplication::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_area_square_meters' => 'decimal:2',
            'established_on' => 'date',
            'started_on' => 'date',
            'registered_on' => 'date',
            'metadata' => 'array',
        ];
    }
}
