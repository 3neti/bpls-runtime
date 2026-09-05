<?php

namespace App\Actions;

use LogicException;

class ResolveOfficialReceiptProfile
{
    /** @return array<string, mixed> */
    public function handle(): array
    {
        $profile = config('municipality.official_receipt');

        if (! is_array($profile)
            || ! is_string(data_get($profile, 'profile_key'))
            || data_get($profile, 'form.accountable_form_number') !== 51
            || ! is_string(data_get($profile, 'collecting_officer.name'))) {
            throw new LogicException('The configured Official Receipt profile is incomplete or invalid.');
        }

        return $profile;
    }
}
