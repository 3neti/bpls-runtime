<?php

namespace App\Assessment\Price;

use App\Models\Assessment;
use LogicException;

final class HistoricalPriceReport
{
    /** @return array<string, mixed> */
    public function forAssessment(Assessment $assessment): array
    {
        $report = $assessment->price_report_snapshot;

        if (! is_array($report)) {
            throw new LogicException("Assessment [{$assessment->id}] predates frozen PriceReport reconstruction.");
        }

        $fingerprint = app(CanonicalFinancialFingerprint::class)->hash($report);
        if (! hash_equals((string) $assessment->price_report_fingerprint, $fingerprint)) {
            throw new LogicException("Assessment [{$assessment->id}] PriceReport fingerprint is inconsistent.");
        }

        return $report;
    }
}
