<?php

namespace App\Data\Application;

use Spatie\LaravelData\Data;

final class ApplicationOfficeData extends Data
{
    /**
     * @param  list<array<string, mixed>>  $responsibilities
     * @param  list<array<string, mixed>>  $payment_orders
     * @param  array{statement: string, officer_name: ?string, certified_at: ?string}|null  $certification
     */
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly string $status,
        public readonly array $responsibilities,
        public readonly array $payment_orders,
        public readonly int $paperless_payment_order_count,
        public readonly int $total_amount_cents,
        public readonly ?array $certification,
    ) {}
}
