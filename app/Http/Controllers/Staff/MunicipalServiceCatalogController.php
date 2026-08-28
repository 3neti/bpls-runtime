<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildMunicipalPriceList;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MunicipalServiceCatalogController extends Controller
{
    public function index(BuildMunicipalPriceList $buildMunicipalPriceList): Response
    {
        Gate::authorize(UserPermission::AccessStaff->value);

        return Inertia::render('services-and-fees/Internal', [
            'priceList' => $buildMunicipalPriceList->handle(includeInternalEvidence: true),
        ]);
    }
}
