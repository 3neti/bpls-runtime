<?php

namespace App\Assessment;

use App\Models\Assessment;
use App\Models\AssessmentLine;
use Illuminate\Support\Arr;

class AssessmentSnapshotFingerprint
{
    /**
     * The canonical fingerprint payload for one Assessment.
     *
     * Each field is canonicalized by its *domain* type, never by the PHP type a
     * database driver happened to return. PostgreSQL computes `SUM(bigint)` as
     * `numeric`, which `pdo_pgsql` surfaces as a string, so an Assessment whose
     * `total_amount_cents` was just assigned from that aggregate carried
     * `'10000'` while every reread carried `10000`. The amount was identical but
     * the fingerprint changed, which correctly tripped the stale-snapshot
     * refusal for a purely representational difference.
     *
     * Only fields whose domain type is unambiguously an integer are coerced, and
     * every one is named explicitly below: identifiers, the version sequence,
     * and minor-unit amounts. There is deliberately no generic rule that turns
     * numeric-looking strings into integers, because `code`, `name`, `basis`,
     * `legal_basis`, and the opaque `source_snapshot` / `rule_snapshot` payloads
     * carry municipal identifiers such as tracking references, permit numbers,
     * OR numbers, and values with significant leading zeroes. Those pass through
     * untouched, as do enum-backed values and ISO-8601 timestamps.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Assessment $assessment): array
    {
        $assessment->loadMissing(['lines' => fn ($query) => $query->orderBy('id')]);

        return $this->normalize([
            'assessment_id' => $this->integer($assessment->id),
            'permit_application_id' => $this->integer($assessment->permit_application_id),
            'sequence' => $this->integer($assessment->sequence),
            'status' => $assessment->status->value,
            'assessed_by_id' => $this->nullableInteger($assessment->assessed_by_id),
            'assessed_at' => $assessment->assessed_at?->toIso8601String(),
            'superseded_at' => $assessment->superseded_at?->toIso8601String(),
            'total_amount_cents' => $this->integer($assessment->total_amount_cents),
            'source_snapshot' => $assessment->source_snapshot,
            'lines' => $assessment->lines
                ->sortBy('id')
                ->values()
                ->map(fn (AssessmentLine $line): array => [
                    'id' => $this->integer($line->id),
                    'permit_application_line_id' => $this->nullableInteger($line->permit_application_line_id),
                    'fee_rule_id' => $this->nullableInteger($line->fee_rule_id),
                    'business_permit_evaluation_item_id' => $this->nullableInteger($line->business_permit_evaluation_item_id),
                    'paperless_payment_order_line_id' => $this->nullableInteger($line->paperless_payment_order_line_id),
                    'line_of_business_id' => $this->nullableInteger($line->line_of_business_id),
                    'code' => $line->code,
                    'name' => $line->name,
                    'category' => $line->category->value,
                    'calculation_type' => $line->calculation_type->value,
                    'basis' => $line->basis,
                    'basis_amount_cents' => $this->integer($line->basis_amount_cents),
                    'amount_cents' => $this->integer($line->amount_cents),
                    'legal_basis' => $line->legal_basis,
                    'rule_snapshot' => $line->rule_snapshot,
                ])
                ->all(),
        ]);
    }

    public function hash(Assessment $assessment): string
    {
        return hash('sha256', json_encode(
            $this->snapshot($assessment),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /**
     * A required integer-domain value: a database identifier, a sequence, or an
     * amount in minor units. Applied only to fields declared non-nullable by
     * the schema.
     */
    private function integer(mixed $value): int
    {
        return (int) $value;
    }

    /**
     * A nullable integer-domain value. Absence stays absent: `null` is never
     * canonicalized to `0`, so "no fee rule" cannot be confused with fee rule 0.
     */
    private function nullableInteger(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function normalize(array $value): array
    {
        if (Arr::isAssoc($value)) {
            ksort($value);
        }

        return array_map(
            fn (mixed $item): mixed => is_array($item) ? $this->normalize($item) : $item,
            $value,
        );
    }
}
