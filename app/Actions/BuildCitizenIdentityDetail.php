<?php

namespace App\Actions;

use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\User;

class BuildCitizenIdentityDetail
{
    /** @return array<string, mixed> */
    public function handle(User $citizen): array
    {
        $owner = $citizen->businessOwner()
            ->select(['id', 'name', 'email', 'phone', 'address'])
            ->with([
                'businesses' => fn ($query) => $query
                    ->select(['id', 'business_owner_id', 'name', 'trade_name'])
                    ->orderBy('name'),
            ])
            ->first();

        if (! $owner instanceof BusinessOwner) {
            return [
                'linked' => false,
                'owner' => null,
                'businesses' => [],
            ];
        }

        return [
            'linked' => true,
            'owner' => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
                'phone' => $owner->phone,
                'address' => $owner->address,
            ],
            'businesses' => $owner->businesses
                ->map(fn (Business $business): array => [
                    'id' => $business->id,
                    'name' => $business->name,
                    'trade_name' => $business->trade_name,
                ])
                ->values()
                ->all(),
        ];
    }
}
