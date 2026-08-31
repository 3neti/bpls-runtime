<?php

namespace App\LifecycleScenarios;

use RuntimeException;
use Throwable;

final class NewApplicationHappyPathFailure extends RuntimeException
{
    public function __construct(
        public readonly string $invariant,
        string $detail,
        ?Throwable $previous = null,
    ) {
        parent::__construct("FAILED AT: {$invariant} — {$detail}", previous: $previous);
    }
}
