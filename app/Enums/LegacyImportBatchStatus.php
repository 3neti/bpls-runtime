<?php

namespace App\Enums;

enum LegacyImportBatchStatus: string
{
    case Staging = 'staging';
    case Staged = 'staged';
    case StagedWithExceptions = 'staged_with_exceptions';
    case Failed = 'failed';
}
