<?php

use App\Enums\PermitApplicationStatus;

test('historical evidence status is distinct from current release authority', function () {
    expect(PermitApplicationStatus::HistoricalEvidence)->not->toBe(PermitApplicationStatus::Released)
        ->and(PermitApplicationStatus::HistoricalEvidence->value)->toBe('historical_evidence');
});
