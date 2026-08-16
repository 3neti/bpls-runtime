<?php

namespace App\Enums;

enum LegacyDocumentObjectStagingStatus: string
{
    case Staging = 'staging';
    case Staged = 'staged';
    case Failed = 'failed';
}
