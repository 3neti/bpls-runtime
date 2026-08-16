<?php

namespace App\Actions;

use App\Exceptions\UnsupportedBillingGroupFinancialPolicy;
use App\Models\BillingGroupRecord;

class RequireBillingGroupFinancialReadiness
{
    public function __construct(private readonly DescribeBillingGroupFinancialReadiness $describeReadiness) {}

    /** @return array<string, mixed> */
    public function handle(BillingGroupRecord $record): array
    {
        $readiness = $this->describeReadiness->handle($record);

        if ($readiness['status'] !== 'ready') {
            throw new UnsupportedBillingGroupFinancialPolicy($record, $readiness);
        }

        return $readiness;
    }
}
