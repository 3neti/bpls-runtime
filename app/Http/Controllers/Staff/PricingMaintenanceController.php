<?php

namespace App\Http\Controllers\Staff;

use App\Actions\RecordPricingRuleReview;
use App\Assessment\PricingRuleReviewSnapshot;
use App\Enums\FeeRuleCategory;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordPricingRuleReviewRequest;
use App\Models\FeeRule;
use App\Models\FeeRuleRevision;
use App\Models\PricingRuleReview;
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
            $detail = [
                ...$this->row($selected),
                'legal_basis' => $selected->legal_basis,
                'effective_from' => $selected->effective_from->toDateString(),
                'effective_until' => $selected->effective_until?->toDateString(),
                'snapshot_sha256' => $hash,
                'reconciliation_id' => $decision?->id,
                'decision_reference' => $decision?->decision_reference,
                'decision_authority' => $decision?->decision_authority,
                'execution_status' => $decision?->execution_status->value,
                'execution_reason' => $decision?->execution_reason,
                'review_status' => $latest === null ? 'Not recorded' : ($hash === $latest->getAttribute('snapshot_sha256') ? 'Current content recorded' : 'Changed since review'),
                'revisions' => $selected->revisions()->where('status', 'proposed')->reorder('version', 'desc')->limit(5)->get()->map(fn (FeeRuleRevision $revision): array => [
                    'id' => $revision->id, 'version' => $revision->version, 'status' => $revision->status,
                    'amount_display' => $revision->proposed_amount_minor === null ? '—' : '₱'.number_format($revision->proposed_amount_minor / 100, 2),
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

    /** @return array<string, mixed> */
    private function row(FeeRule $rule): array
    {
        $amount = $this->presentation->amountAndBasis($rule);

        return [
            'id' => $rule->id, 'name' => $rule->name, 'code' => $rule->code,
            'category' => $rule->feeCategory->name ?? ucfirst($rule->category->value),
            'division' => $rule->businessDivision?->name,
            'method' => $rule->calculation_type->value,
            'amount_display' => $amount['value'], 'basis' => $amount['basis'],
            'revenue_code' => $rule->revenueAccount?->code,
            'is_active' => $rule->is_active,
        ];
    }
}
