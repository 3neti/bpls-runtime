<?php

namespace App\Http\Controllers\Staff;

use App\Actions\PublishFeeRuleRevision;
use App\Actions\RecordPricingRuleReview;
use App\Assessment\PricingDefinitionValidator;
use App\Assessment\PricingRuleReviewSnapshot;
use App\Assessment\PublishedFeeRuleResolver;
use App\Enums\FeeRuleCategory;
use App\Enums\UserPermission;
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordPricingRuleReviewRequest;
use App\Http\Requests\StorePricingGroupRequest;
use App\Models\FeeRule;
use App\Models\FeeRulePublication;
use App\Models\FeeRuleRevision;
use App\Models\PricingChargeGroup;
use App\Models\PricingRuleReview;
use App\Models\RevenueAccount;
use App\Support\MunicipalFeeCatalogPresentation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PricingMaintenanceController extends Controller
{
    public function __construct(
        private readonly MunicipalFeeCatalogPresentation $presentation,
        private readonly PricingRuleReviewSnapshot $snapshots,
        private readonly PublishedFeeRuleResolver $publishedPrices,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize(UserPermission::ViewFeeRules->value);
        $filters = [
            'search' => mb_substr(trim($request->string('search')->toString()), 0, 150),
            'category' => in_array($request->query('category'), ['fee', 'tax', 'clearance', 'other'], true) ? $request->query('category') : '',
            'status' => in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : '',
        ];
        $query = FeeRule::query()->with(['revenueAccount', 'feeCategory', 'businessDivision', 'currentReconciliation', 'ranges']);
        if ($filters['search'] !== '') {
            $search = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('code', 'like', $search)
                ->orWhereHas('revenueAccount', fn ($a) => $a->where('code', 'like', $search)));
        }
        if ($filters['category'] !== '') {
            $query->where('category', $filters['category']);
        }
        if ($filters['status'] !== '') {
            $query->where('is_active', $filters['status'] === 'active');
        }
        $rules = $query->orderBy('name')->orderBy('id')->paginate(20)->withQueryString();
        $selectedId = $request->integer('rule');
        $selected = $selectedId > 0
            ? FeeRule::query()->with(['revenueAccount', 'feeCategory', 'businessDivision', 'currentReconciliation', 'ranges'])->findOrFail($selectedId)
            : $rules->first();
        $detail = null;
        if ($selected instanceof FeeRule) {
            $decision = $selected->currentReconciliation;
            $hash = $decision === null ? null : $this->snapshots->hash($this->snapshots->capture($selected, $decision));
            $reviews = PricingRuleReview::query()->where('fee_rule_id', $selected->id)->latest('id')->limit(8)->get();
            $latest = $reviews->first();
            $publications = FeeRulePublication::query()->where('fee_rule_id', $selected->id)->get()->keyBy('fee_rule_revision_id');
            try {
                $currentPrice = $this->publishedPrices->forYear($selected, (int) now()->year);
            } catch (UnsupportedAssessmentPolicy) {
                $currentPrice = $selected;
            }
            $detail = [
                ...$this->row($selected),
                'source_basis' => $selected->basis,
                'revenue_account_id' => $currentPrice->getAttribute('revenue_account_id'),
                'group_id' => data_get($currentPrice->metadata, 'pricing_publication.group.id'),
                'ranges' => $currentPrice->ranges->map->only(['min_basis_cents', 'max_basis_cents', 'amount_cents'])->values()->all(),
                'legal_basis' => $selected->legal_basis,
                'effective_from' => data_get($currentPrice->metadata, 'pricing_publication.effective_from', $selected->effective_from->toDateString()),
                'effective_until' => data_get($currentPrice->metadata, 'pricing_publication.effective_until', $selected->effective_until?->toDateString()),
                'snapshot_sha256' => $hash,
                'reconciliation_id' => $decision?->id,
                'decision_reference' => $decision?->decision_reference,
                'decision_authority' => $decision?->decision_authority,
                'execution_status' => $decision?->execution_status->value,
                'execution_reason' => $decision?->execution_reason,
                'review_status' => $latest === null ? 'Not recorded' : ($hash === $latest->getAttribute('snapshot_sha256') ? 'Current content recorded' : 'Changed since review'),
                'revisions' => $selected->revisions()->where('status', 'proposed')->reorder('version', 'desc')->limit(5)->get()->map(fn (FeeRuleRevision $revision): array => [
                    'id' => $revision->id, 'version' => $revision->version, 'status' => $publications->has($revision->id) ? 'published' : $revision->status,
                    'can_publish' => $selected->is_active && (new PricingDefinitionValidator)->supportsCategory($selected)
                        && ! $publications->has($revision->id) && isset($revision->snapshot['publication_base_sha256']),
                    'amount_display' => $selected->calculation_type->value === 'range'
                        ? count($revision->snapshot['definition_changes']['ranges'] ?? []).' brackets'
                        : ($revision->proposed_amount_minor === null ? '—' : '₱'.number_format($revision->proposed_amount_minor / 100, 2).($selected->basis === 'employee_count' ? ' per employee' : '')),
                    'effective_from' => $revision->effective_from->toDateString(),
                    'effective_until' => $revision->effective_until?->toDateString(),
                    'reason' => $revision->reason, 'authority' => $revision->authority,
                ])->all(),
                'reviews' => $reviews->map(fn (PricingRuleReview $review): array => [
                    'id' => $review->id, 'reference' => $review->getAttribute('review_reference'),
                    'recorded_at' => $review->created_at?->toIso8601String(),
                ])->all(),
            ];
        }

        return Inertia::render('pricing-maintenance/Index', [
            'rules' => $rules->through(fn (FeeRule $rule): array => $this->row($rule)),
            'selected' => $detail, 'filters' => $filters,
            'groups' => PricingChargeGroup::query()->orderBy('name')->get(['id', 'code', 'name']),
            'accounts' => RevenueAccount::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'categories' => array_map(fn (FeeRuleCategory $category): array => ['value' => $category->value, 'label' => ucfirst($category->value)], FeeRuleCategory::cases()),
            'canManage' => $request->user()?->can(UserPermission::ManageFeeRules->value) ?? false,
            'summary' => ['rules' => FeeRule::query()->count(), 'missing_accounts' => FeeRule::query()->whereNull('revenue_account_id')->count()],
        ]);
    }

    public function recordReview(RecordPricingRuleReviewRequest $request, FeeRule $feeRule, RecordPricingRuleReview $record): RedirectResponse
    {
        $data = $request->validated();
        $record->handle($feeRule, $request->user(), (int) $data['reconciliation_id'], $data['snapshot_sha256'], $data['review_reference']);

        return back()->with('status', 'Review recorded. Current prices are unchanged.');
    }

    public function publish(Request $request, FeeRuleRevision $revision, PublishFeeRuleRevision $publish): RedirectResponse
    {
        $publish->handle($revision, $request->user());

        return back()->with('status', 'Price revision published. Frozen assessments are unchanged.');
    }

    public function storeGroup(StorePricingGroupRequest $request): RedirectResponse
    {
        PricingChargeGroup::query()->create($request->validated());

        return back()->with('status', 'Fee group added.');
    }

    /** @return array<string, mixed> */
    private function row(FeeRule $rule): array
    {
        $issue = null;
        try {
            $rule = $this->publishedPrices->forYear($rule, (int) now()->year);
        } catch (UnsupportedAssessmentPolicy $exception) {
            $issue = $exception->getMessage();
        }
        $amount = $this->presentation->amountAndBasis($rule);

        return [
            'id' => $rule->id, 'name' => $rule->name, 'code' => $rule->code,
            'category' => $rule->feeCategory->name ?? ucfirst($rule->category->value),
            'division' => $rule->businessDivision?->name,
            'method' => $rule->calculation_type->value,
            'amount_display' => $issue === null ? $amount['value'] : 'Unavailable',
            'basis' => $issue ?? $amount['basis'],
            'price_year' => (int) now()->year,
            'publication_id' => data_get($rule->metadata, 'pricing_publication.id'),
            'revenue_code' => $rule->revenueAccount?->code,
            'is_active' => $rule->is_active,
        ];
    }
}
