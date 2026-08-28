<?php

namespace App\Http\Controllers;

use App\Actions\BuildMunicipalPriceList;
use Inertia\Inertia;
use Inertia\Response;

class PublicMunicipalServiceCatalogController extends Controller
{
    public function __invoke(BuildMunicipalPriceList $buildMunicipalPriceList): Response
    {
        return Inertia::render('public/ServicesAndFees', [
            'priceList' => $buildMunicipalPriceList->handle(),
        ]);
    }
}
