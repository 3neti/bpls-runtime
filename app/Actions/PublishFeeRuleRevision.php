<?php

namespace App\Actions;

use App\Assessment\PricingDefinitionValidator;
use App\Assessment\PricingPublicationSnapshot;
use App\Assessment\PricingRuleReviewSnapshot;
use App\Enums\FeeRuleCategory;
use App\Enums\UserPermission;
use App\Models\FeeRule;
use App\Models\FeeRulePublication;
use App\Models\FeeRuleRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class PublishFeeRuleRevision
{
    public function __construct(private readonly PricingPublicationSnapshot $snapshots, private readonly PricingRuleReviewSnapshot $hashes) {}

    public function handle(FeeRuleRevision $revision, User $actor): FeeRulePublication
    {
        Gate::forUser($actor)->authorize(UserPermission::ManageFeeRules->value);
        Gate::forUser($actor)->authorize(UserPermission::ViewFeeRules->value);

        return DB::transaction(function () use ($revision, $actor): FeeRulePublication {
            $rule = FeeRule::query()->whereKey($revision->fee_rule_id)->lockForUpdate()->firstOrFail();
            $revision = FeeRuleRevision::query()->whereKey($revision->id)->lockForUpdate()->firstOrFail();
            $existing = FeeRulePublication::query()->where('fee_rule_revision_id', $revision->id)->first();
            if ($existing !== null) {
                return $existing;
            }
            $base = $this->snapshots->capture($rule);
            if (($revision->snapshot['publication_base_sha256'] ?? null) !== $this->hashes->hash($base)) {
                throw ValidationException::withMessages(['publication' => 'The source rule changed or this proposal predates publication support. Prepare a fresh revision.']);
            }
            if (! $rule->is_active || $rule->category !== FeeRuleCategory::Fee
                || $revision->status !== 'proposed' || $revision->currency !== 'PHP'
                || $revision->proposed_amount_minor === null || $revision->proposed_amount_minor < 0
                || blank($revision->authority) || blank($revision->reason)) {
                throw ValidationException::withMessages(['publication' => 'Publication requires an active fee, an explicit amount, reason and authority.']);
            }
            if ($revision->effective_from->lt($rule->effective_from)
                || ($rule->effective_until !== null && ($revision->effective_until === null || $revision->effective_until->gt($rule->effective_until)))
                || ($revision->effective_until !== null && $revision->effective_until->lt($revision->effective_from))) {
                throw ValidationException::withMessages(['publication' => 'The revision period must fit within the source rule period.']);
            }
            if (FeeRulePublication::query()->where('fee_rule_id', $rule->id)->whereDate('effective_from', $revision->effective_from)->exists()) {
                throw ValidationException::withMessages(['publication' => 'A published revision already starts on this date. Choose a later effective date.']);
            }
            $definition = (new PricingDefinitionValidator)->resolve($rule, $revision->proposed_amount_minor, $revision->snapshot['definition_changes'] ?? []);
            $snapshot = ['schema_version' => 1, 'base' => $base, 'definition' => $definition, 'revision' => $revision->attributesToArray(), 'selection_basis' => 'application_tax_year_january_1'];
            $publication = FeeRulePublication::query()->create([
                'fee_rule_id' => $rule->id, 'fee_rule_revision_id' => $revision->id, 'published_by_id' => $actor->id,
                'effective_from' => $revision->effective_from, 'effective_until' => $revision->effective_until,
                'snapshot' => $snapshot, 'snapshot_sha256' => $this->hashes->hash($snapshot), 'published_at' => now(),
            ]);
            $rule->auditEvents()->create(['fee_rule_revision_id' => $revision->id, 'event' => 'revision_published', 'actor_id' => $actor->id, 'occurred_at' => now(), 'snapshot' => ['publication_id' => $publication->id, 'sha256' => $publication->getAttribute('snapshot_sha256')]]);

            return $publication;
        });
    }
}
