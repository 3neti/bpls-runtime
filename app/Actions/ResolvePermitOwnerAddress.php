<?php

namespace App\Actions;

use App\Models\PermitApplication;
use Illuminate\Support\Str;

final class ResolvePermitOwnerAddress
{
    /** @var list<string> */
    private const array AddressParts = [
        'house_or_building_number',
        'building_name',
        'unit_number',
        'street',
        'subdivision',
        'barangay',
        'city_municipality',
        'province',
    ];

    public function handle(PermitApplication $permitApplication): ?string
    {
        $permitApplication->loadMissing(['declaration', 'business.owner']);

        $declaration = $permitApplication->declaration?->snapshot;
        if (! is_array($declaration)) {
            $declaration = data_get($permitApplication->metadata, 'applicant_declaration_draft');
        }

        $declaredAddress = is_array($declaration)
            ? data_get($declaration, 'owner_address')
            : null;

        if (is_array($declaredAddress)) {
            $parts = collect(self::AddressParts)
                ->map(fn (string $key): ?string => $this->text(data_get($declaredAddress, $key)))
                ->filter()
                ->unique(fn (string $part): string => Str::lower($part))
                ->values();

            if ($parts->isNotEmpty()) {
                return $parts->implode(', ');
            }
        }

        return $this->text($permitApplication->business->owner->address);
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value, " \n\r\t\v\0,");

        return $text === '' ? null : $text;
    }
}
