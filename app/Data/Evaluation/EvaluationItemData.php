<?php

namespace App\Data\Evaluation;

use Spatie\LaravelData\Data;

/**
 * One Evaluation item. The canonical item taxonomy (Fact / Determination /
 * Charge) is preserved verbatim in `item_type` — this contract does not
 * introduce a fourth kind. Confirmation/correction/proposal/override are
 * provenance recorded in `history`, not separate item kinds.
 *
 * The default/system proposal and the currently resolved value are kept
 * as two distinct fields on purpose (see the Board invariant that every
 * peso must be explainable): a UI must never collapse them into one
 * `amount`.
 */
class EvaluationItemData extends Data
{
    /**
     * @param  array<int, EvaluationRevisionData>  $history
     */
    public function __construct(
        public readonly int $id,
        public readonly string $key,
        public readonly string $label,
        public readonly ?int $line_of_business_id,
        public readonly ?string $line_of_business_name,
        public readonly ?string $department_selection_reason,
        public readonly string $item_type,
        public readonly string $responsible_party,
        public readonly bool $is_required,
        public readonly bool $requires_confirmation,
        public readonly bool $is_mine,
        public readonly string $applicability,
        public readonly string $resolution,
        public readonly ?string $action,
        public readonly mixed $default_value,
        public readonly ?string $default_source_classification,
        public readonly mixed $resolved_value,
        public readonly ?string $source_classification,
        public readonly ?string $reason,
        public readonly ?string $occurred_at,
        public readonly bool $inspection_required,
        public readonly array $history,
    ) {}
}
