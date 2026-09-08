<?php

namespace App\Http\Controllers\Staff;

use App\Actions\ReconcileLegacyFeeCatalogCandidate;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ReconcileLegacyFeeCatalogCandidateRequest;
use App\Models\FeeRule;
use App\Models\LegacyFeeRuleReconciliation;
use App\Models\LegacyImportBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class LegacyFeeCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(UserPermission::ViewFeeRules->value);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'calculation_type' => ['nullable', Rule::in(['constant', 'range', 'formula'])],
            'scope' => ['nullable', Rule::in(['application_wide', 'direct_group', 'inherited_division'])],
            'disposition' => ['nullable', Rule::in(['pending', 'map_existing', 'create_proposed', 'quarantine'])],
        ]);
        $batch = LegacyImportBatch::query()
            ->with('source')
            ->whereHas('records', fn ($query) => $query->where('dataset_key', 'fees'))
            ->latest('id')
            ->first();
        $baseQuery = LegacyFeeRuleReconciliation::query()
            ->with('feeRule')
            ->where('source_dataset', 'fees')
            ->when($batch, fn ($query, LegacyImportBatch $batch) => $query->where('metadata->batch_id', $batch->id));
        $summaryQuery = clone $baseQuery;
        $candidates = $baseQuery
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('metadata->name', 'like', "%{$search}%")
                        ->orWhere('metadata->division_name', 'like', "%{$search}%")
                        ->orWhereHas('feeRule', fn ($query) => $query->where('code', 'like', "%{$search}%"));
                });
            })
            ->when($filters['calculation_type'] ?? null, fn ($query, string $type) => $query->where('metadata->calculation_type', $type))
            ->when($filters['scope'] ?? null, fn ($query, string $scope) => $query->where('metadata->applicability_scope', $scope))
            ->when($filters['disposition'] ?? null, function ($query, string $disposition): void {
                if ($disposition === 'pending') {
                    $query->whereNull('metadata->review_disposition');
                } else {
                    $query->where('metadata->review_disposition', $disposition);
                }
            })
            ->orderBy('metadata->name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (LegacyFeeRuleReconciliation $candidate): array => $this->candidatePayload($candidate));

        return Inertia::render('fee-rules/LegacyCandidates', [
            'filters' => [
                'q' => $filters['q'] ?? '',
                'calculation_type' => $filters['calculation_type'] ?? '',
                'scope' => $filters['scope'] ?? '',
                'disposition' => $filters['disposition'] ?? '',
            ],
            'batch' => $batch ? [
                'id' => $batch->id,
                'run_reference' => $batch->run_reference,
                'source_title' => $batch->source->title,
                'captured_at' => $batch->source->baseline,
                'archive_sha256' => $batch->source->archive_checksum,
                'manifest_sha256' => $batch->manifest_checksum,
            ] : null,
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'constant' => (clone $summaryQuery)->where('metadata->calculation_type', 'constant')->count(),
                'range' => (clone $summaryQuery)->where('metadata->calculation_type', 'range')->count(),
                'formula' => (clone $summaryQuery)->where('metadata->calculation_type', 'formula')->count(),
                'proposed' => (clone $summaryQuery)->whereNotNull('fee_rule_id')->count(),
                'quarantined' => (clone $summaryQuery)->where('metadata->review_disposition', 'quarantine')->count(),
                'executable' => 0,
            ],
            'candidates' => $candidates,
            'feeRuleOptions' => FeeRule::query()->orderBy('name')->orderBy('code')->get()->map(fn (FeeRule $rule): array => [
                'id' => $rule->id,
                'code' => $rule->code,
                'name' => $rule->name,
                'active' => $rule->is_active,
            ])->values()->all(),
            'canManage' => $request->user()?->can(UserPermission::ManageFeeRules->value) ?? false,
        ]);
    }

    public function reconcile(ReconcileLegacyFeeCatalogCandidateRequest $request, LegacyFeeRuleReconciliation $legacyFeeRuleReconciliation, ReconcileLegacyFeeCatalogCandidate $reconcile): RedirectResponse
    {
        Gate::authorize(UserPermission::ManageFeeRules->value);
        $data = $request->validated();
        $reconcile->handle(
            $legacyFeeRuleReconciliation,
            $data['action'],
            $data['fee_rule_id'] ?? null,
            $data['reason'],
            $request->user(),
        );

        return back()->with('status', 'Legacy catalogue review recorded. No fee was activated.');
    }

    /** @return array<string, mixed> */
    private function candidatePayload(LegacyFeeRuleReconciliation $candidate): array
    {
        $metadata = $candidate->metadata ?? [];

        return [
            'id' => $candidate->id,
            'name' => data_get($metadata, 'name'),
            'calculation_type' => data_get($metadata, 'calculation_type'),
            'legacy_fee_category' => data_get($metadata, 'legacy_fee_category'),
            'application_types' => data_get($metadata, 'application_types', []),
            'amount_minor' => data_get($metadata, 'amount_minor'),
            'range_field' => data_get($metadata, 'range_field'),
            'ranges' => data_get($metadata, 'ranges', []),
            'range_count' => data_get($metadata, 'range_count', 0),
            'formula' => data_get($metadata, 'formula'),
            'applicability_scope' => data_get($metadata, 'applicability_scope'),
            'division_name' => data_get($metadata, 'division_name'),
            'applicable_group_names' => data_get($metadata, 'applicable_group_names', []),
            'applicable_group_count' => data_get($metadata, 'applicable_group_count', 0),
            'overrides' => data_get($metadata, 'overrides', []),
            'override_count' => data_get($metadata, 'override_count', 0),
            'duplicate_name_count' => data_get($metadata, 'duplicate_name_count', 0),
            'exact_name_target_count' => data_get($metadata, 'exact_name_target_count', 0),
            'blockers' => data_get($metadata, 'blockers', []),
            'review_disposition' => data_get($metadata, 'review_disposition', 'pending'),
            'review_reason' => data_get($metadata, 'review_reason'),
            'fee_rule' => $candidate->feeRule ? [
                'id' => $candidate->feeRule->id,
                'code' => $candidate->feeRule->code,
                'name' => $candidate->feeRule->name,
                'active' => $candidate->feeRule->is_active,
            ] : null,
            'source_payload_sha256' => data_get($metadata, 'source_payload_sha256'),
        ];
    }
}
