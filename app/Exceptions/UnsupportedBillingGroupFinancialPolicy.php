<?php

namespace App\Exceptions;

use App\Models\BillingGroupRecord;
use RuntimeException;

class UnsupportedBillingGroupFinancialPolicy extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $readiness
     */
    public function __construct(
        public readonly BillingGroupRecord $record,
        public readonly array $readiness,
    ) {
        parent::__construct(
            "Billing-group draft [{$record->draft_reference}] is not financially executable: ".implode(', ', $readiness['blocked_by']).'.'
        );
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'billing_group_record_id' => $this->record->id,
            'draft_reference' => $this->record->draft_reference,
            'blocked_by' => $this->readiness['blocked_by'],
        ];
    }
}
