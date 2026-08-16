<?php

namespace App\Actions;

use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\LegacyRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LegacyRegistryMappingProjector
{
    /** @return array{attributes: array<string, mixed>, identity: array<string, mixed>, reasons: list<string>, blocked: bool} */
    public function owner(LegacyRecord $record): array
    {
        $payload = $record->payload;
        $firstName = $this->string($payload, 'firstName');
        $middleName = $this->string($payload, 'middleName');
        $lastName = $this->string($payload, 'lastName');
        $ownerType = $this->string($payload, 'ownerType');
        $groupName = $this->string($payload, 'groupName');
        $individualName = Str::of(implode(' ', array_filter([$firstName, $middleName, $lastName])))->squish()->toString();
        $name = $ownerType === 'Group' && $groupName !== '' ? $groupName : $individualName;
        $reasons = [];
        $blocked = false;

        if ($name === '') {
            $reasons[] = 'required_owner_name_missing';
            $blocked = true;
        }

        if ($ownerType === 'Group') {
            $reasons[] = 'group_owner_semantics_require_reconciliation';
        }

        if (($payload['isDeleted'] ?? false) === true) {
            $reasons[] = 'soft_deleted_record_policy_unresolved';
        }

        if (($payload['isBlacklisted'] ?? false) === true || $this->string($payload, 'blacklistReason') !== '') {
            $reasons[] = 'blacklist_state_requires_registry_policy';
        }

        $attributes = [
            'name' => $name,
            'email' => $this->stringOrNull($payload, 'email'),
            'phone' => $this->stringOrNull($payload, 'mobile'),
            'address' => $this->stringOrNull($payload, 'address'),
            'legacy_source_id' => $record->legacy_id,
            'metadata' => [
                'legacy_owner_type' => $ownerType !== '' ? $ownerType : null,
                'legacy_group_name' => $groupName !== '' ? $groupName : null,
                'legacy_birth_date' => $this->stringOrNull($payload, 'birthDate'),
                'legacy_civil_status' => $this->stringOrNull($payload, 'civilStatus'),
                'legacy_gender' => $this->stringOrNull($payload, 'gender'),
                'legacy_citizenship' => $this->stringOrNull($payload, 'citizenship'),
                'legacy_tin' => $this->stringOrNull($payload, 'tin'),
                'legacy_location_ids_preserved' => array_filter([
                    'provinceId' => $this->stringOrNull($payload, 'provinceId'),
                    'cityId' => $this->stringOrNull($payload, 'cityId'),
                    'barangayId' => $this->stringOrNull($payload, 'barangayId'),
                ]),
            ],
        ];

        return [
            'attributes' => $attributes,
            'identity' => [
                'name' => $this->normalize($name),
                'birth_date' => $this->normalize($this->string($payload, 'birthDate')),
                'tin' => $this->normalize($this->string($payload, 'tin')),
                'email' => $this->normalize($this->string($payload, 'email')),
                'phone' => $this->normalizePhone($this->string($payload, 'mobile')),
            ],
            'reasons' => $reasons,
            'blocked' => $blocked,
        ];
    }

    /** @return array{attributes: array<string, mixed>, identity: array<string, mixed>, reasons: list<string>, blocked: bool, owner_legacy_id: string|null} */
    public function business(LegacyRecord $record): array
    {
        $payload = $record->payload;
        $name = $this->string($payload, 'name');
        $ownerLegacyId = $this->stringOrNull($payload, 'ownerId');
        $reasons = [];
        $blocked = false;

        if ($name === '') {
            $reasons[] = 'required_business_name_missing';
            $blocked = true;
        }

        if ($ownerLegacyId === null) {
            $reasons[] = 'required_business_owner_reference_missing';
            $blocked = true;
        }

        foreach (['provinceId', 'cityId', 'barangayId', 'categoryId', 'subCategoryId'] as $referenceField) {
            if ($this->string($payload, $referenceField) !== '') {
                $reasons[] = 'reference_data_mapping_required';
                break;
            }
        }

        if (($payload['isDeleted'] ?? false) === true) {
            $reasons[] = 'soft_deleted_record_policy_unresolved';
        }

        if (($payload['isBlacklisted'] ?? false) === true || $this->string($payload, 'blacklistReason') !== '') {
            $reasons[] = 'blacklist_state_requires_registry_policy';
        }

        $attributes = [
            'name' => $name,
            'registration_number' => $this->stringOrNull($payload, 'registrationNumber'),
            'address' => $this->stringOrNull($payload, 'address'),
            'barangay' => $this->stringOrNull($payload, 'barangay'),
            'ownership_type' => $this->stringOrNull($payload, 'ownershipType'),
            'organization_name' => $this->stringOrNull($payload, 'groupName'),
            'occupancy' => $this->stringOrNull($payload, 'occupancy'),
            'building_name' => $this->stringOrNull($payload, 'buildingName'),
            'property_index_number' => $this->stringOrNull($payload, 'propertyIndexNumber'),
            'business_area_square_meters' => $payload['businessArea'] ?? null,
            'male_employee_count' => $payload['maleEmployeeCount'] ?? null,
            'female_employee_count' => $payload['femaleEmployeeCount'] ?? null,
            'contact_number' => $this->stringOrNull($payload, 'contactNumber'),
            'email' => $this->stringOrNull($payload, 'email'),
            'established_on' => $this->stringOrNull($payload, 'establishedDate'),
            'started_on' => $this->stringOrNull($payload, 'dateStarted'),
            'registered_on' => $this->stringOrNull($payload, 'registrationDate'),
            'legacy_source_id' => $record->legacy_id,
            'metadata' => [
                'legacy_business_scale' => $this->stringOrNull($payload, 'businessScale'),
                'legacy_annual_revenue' => $this->stringOrNull($payload, 'annualRevenue'),
                'legacy_permit_payment_cadence' => $this->stringOrNull($payload, 'permitPaymentCadence'),
                'legacy_location_ids_preserved' => array_filter([
                    'provinceId' => $this->stringOrNull($payload, 'provinceId'),
                    'cityId' => $this->stringOrNull($payload, 'cityId'),
                    'barangayId' => $this->stringOrNull($payload, 'barangayId'),
                ]),
                'legacy_classification_ids_preserved' => array_filter([
                    'categoryId' => $this->stringOrNull($payload, 'categoryId'),
                    'subCategoryId' => $this->stringOrNull($payload, 'subCategoryId'),
                ]),
            ],
        ];

        return [
            'attributes' => $attributes,
            'identity' => [
                'owner_legacy_id' => $ownerLegacyId,
                'name' => $this->normalize($name),
                'registration_number' => $this->normalize($this->string($payload, 'registrationNumber')),
            ],
            'reasons' => $reasons,
            'blocked' => $blocked,
            'owner_legacy_id' => $ownerLegacyId,
        ];
    }

    /** @param array<string, mixed> $value */
    public function hashCanonical(array $value): string
    {
        return hash('sha256', $this->canonicalJson($value));
    }

    public function targetSnapshotHash(Model $model): string
    {
        $attributes = $model instanceof BusinessOwner
            ? $model->only(['name', 'email', 'phone', 'address', 'legacy_source_id', 'metadata'])
            : $model->only(['business_owner_id', 'name', 'trade_name', 'registration_number', 'address', 'barangay', 'ownership_type', 'organization_name', 'occupancy', 'building_name', 'property_index_number', 'business_area_square_meters', 'male_employee_count', 'female_employee_count', 'contact_number', 'email', 'established_on', 'started_on', 'registered_on', 'legacy_source_id', 'metadata']);

        return $this->hashCanonical($attributes);
    }

    public function registrySnapshotHash(): string
    {
        $context = hash_init('sha256');

        foreach (BusinessOwner::query()->select(['id', 'name', 'email', 'phone', 'address', 'legacy_source_id', 'updated_at'])->orderBy('id')->cursor() as $owner) {
            hash_update($context, $this->canonicalJson(['owner', ...$owner->getAttributes()]));
        }

        foreach (Business::query()->select(['id', 'business_owner_id', 'name', 'registration_number', 'legacy_source_id', 'updated_at'])->orderBy('id')->cursor() as $business) {
            hash_update($context, $this->canonicalJson(['business', ...$business->getAttributes()]));
        }

        return hash_final($context);
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->squish()->lower()->toString();
    }

    private function normalizePhone(string $value): string
    {
        return (string) preg_replace('/\D+/', '', $value);
    }

    /** @param array<string, mixed> $payload */
    private function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) || is_int($value) ? Str::of((string) $value)->trim()->toString() : '';
    }

    /** @param array<string, mixed> $payload */
    private function stringOrNull(array $payload, string $key): ?string
    {
        $value = $this->string($payload, $key);

        return $value === '' ? null : $value;
    }

    /** @param array<array-key, mixed> $value */
    private function canonicalJson(array $value): string
    {
        return json_encode($this->sortRecursively($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @param array<array-key, mixed> $value
     * @return array<array-key, mixed>
     */
    private function sortRecursively(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursively($item);
            }
        }

        return $value;
    }
}
