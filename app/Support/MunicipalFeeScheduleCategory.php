<?php

namespace App\Support;

use Illuminate\Support\Str;

final class MunicipalFeeScheduleCategory
{
    /** @return array{key: string, label: string} */
    public function forCode(string $code): array
    {
        return match (true) {
            Str::startsWith($code, 'MRC-2A') => ['key' => 'business_taxes', 'label' => 'Business Taxes'],
            Str::startsWith($code, 'MRC-2B') => ['key' => 'markets_mobile_trade', 'label' => 'Markets, Peddlers & Mobile Trade'],
            Str::startsWith($code, 'MRC-2F') => ['key' => 'business_taxes', 'label' => 'Business Taxes'],
            Str::startsWith($code, 'MRC-3A-04') => ['key' => 'inspections_certificates', 'label' => 'Inspections & Certificates'],
            Str::startsWith($code, 'MRC-3A') => ['key' => 'business_permits', 'label' => "Mayor's Permit & Business Licensing"],
            Str::startsWith($code, ['MRC-3B', 'MRC-3C']) => ['key' => 'cockpit_events', 'label' => 'Cockpit & Special Events'],
            Str::startsWith($code, ['MRC-3E', 'MRC-3J']) => ['key' => 'special_permits', 'label' => 'Filming, Parades & Special Permits'],
            Str::startsWith($code, ['MRC-3D', 'MRC-3F']) => ['key' => 'animals_agriculture', 'label' => 'Animals & Agricultural Services'],
            Str::startsWith($code, 'MRC-3G') => ['key' => 'public_works', 'label' => 'Street Excavation & Public Works'],
            Str::startsWith($code, ['MRC-3H', 'MRC-3I']) => ['key' => 'weights_measures', 'label' => 'Weights, Measures & Fuel Pumps'],
            Str::startsWith($code, 'MRC-3K') => ['key' => 'equipment', 'label' => 'Equipment & Machinery'],
            Str::startsWith($code, 'MRC-3L') => ['key' => 'transport', 'label' => 'Transport & Tricycle Services'],
            default => ['key' => 'other', 'label' => 'Other Municipal Services'],
        };
    }
}
