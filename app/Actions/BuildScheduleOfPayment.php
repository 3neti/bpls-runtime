<?php

namespace App\Actions;

use App\Data\Application\ScheduleOfPaymentData;
use App\Models\Assessment;
use LogicException;

class BuildScheduleOfPayment
{
    /** @param array<string, mixed> $priceReport */
    public function handle(Assessment $assessment, array $priceReport): ScheduleOfPaymentData
    {
        $components = data_get($priceReport, 'components', []);
        if (! is_array($components)) {
            throw new LogicException('Schedule of Payment requires canonical PriceReport components.');
        }

        $groups = collect($components)
            ->groupBy(function (array $component): string {
                $office = data_get($component, 'responsible_office');
                if (is_string($office) && $office !== '') {
                    return 'office:'.$office;
                }

                $lineId = data_get($component, 'line_of_business_id');

                return $lineId === null ? 'treasury:application' : 'treasury:lob:'.$lineId;
            })
            ->map(function ($items, string $key): array {
                $first = $items->first();
                $label = str_starts_with($key, 'office:')
                    ? str((string) data_get($first, 'responsible_office'))->headline()->toString()
                    : ('Treasury / '.((string) data_get($first, 'line_of_business_name', 'Application')));

                return [
                    'key' => $key,
                    'label' => $label,
                    'source' => str_starts_with($key, 'office:') ? 'concerned_office_payment_order' : 'treasury_lob',
                    'items' => $items->map(fn (array $item): array => [
                        'exact_once_key' => $item['exact_once_key'],
                        'code' => $item['key'],
                        'name' => $item['label'],
                        'amount_minor' => $item['resolved_minor'],
                    ])->values()->all(),
                    'subtotal_minor' => (int) $items->sum('resolved_minor'),
                ];
            })->values()->all();
        $groups = array_values($groups);

        $scheduleTotal = (int) collect($groups)->sum('subtotal_minor');
        $priceTotal = (int) data_get($priceReport, 'total.minor');
        if ($scheduleTotal !== $priceTotal || $priceTotal !== $assessment->total_amount_cents) {
            throw new LogicException('Schedule of Payment, PriceReport, and Assessment totals must be identical.');
        }

        return new ScheduleOfPaymentData(
            schema_version: 'bpls.schedule-of-payment.v1',
            currency: $assessment->currency,
            groups: $groups,
            grand_total_minor: $scheduleTotal,
            price_report_total_minor: $priceTotal,
            assessment_total_minor: $assessment->total_amount_cents,
            reconciled: true,
            price_report_fingerprint: $assessment->price_report_fingerprint,
        );
    }
}
