<?php

namespace App\Enums;

enum FeeCatalogVersionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Superseded = 'superseded';
}
