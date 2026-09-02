<?php

namespace App\Models\Builders;

use App\Actions\PermitApplicationStatusMutation;
use App\Models\PermitApplication;
use Illuminate\Database\Eloquent\Builder;

/** @extends Builder<PermitApplication> */
class PermitApplicationBuilder extends Builder
{
    /** @param array<string, mixed> $values */
    public function update(array $values)
    {
        if (array_key_exists('status', $values)) {
            PermitApplicationStatusMutation::assertPrivileged();
        }

        return parent::update($values);
    }
}
