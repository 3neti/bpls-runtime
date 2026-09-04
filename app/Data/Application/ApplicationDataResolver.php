<?php

namespace App\Data\Application;

use App\Models\PermitApplication;
use App\Models\User;

interface ApplicationDataResolver
{
    public function resolve(PermitApplication $permitApplication, ?User $viewer = null): ApplicationData;
}
