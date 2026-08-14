<?php

namespace App\Http\Controllers\Staff;

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Models\FeeRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FeeRuleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(UserPermission::ViewFeeRules->value);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', Rule::enum(FeeRuleCategory::class)],
            'scope' => ['nullable', Rule::enum(FeeRuleScope::class)],
            'calculation_type' => ['nullable', Rule::enum(FeeRuleCalculationType::class)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $feeRules = FeeRule::query()
            ->with('lineOfBusiness')
            ->withCount('ranges')
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('legal_basis', 'like', "%{$search}%")
                        ->orWhere('legacy_source_id', 'like', "%{$search}%")
                        ->orWhereHas('lineOfBusiness', function ($query) use ($search): void {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->when($filters['scope'] ?? null, fn ($query, string $scope) => $query->where('scope', $scope))
            ->when($filters['calculation_type'] ?? null, fn ($query, string $calculationType) => $query->where('calculation_type', $calculationType))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (FeeRule $feeRule): array => $this->feeRulePayload($feeRule));

        return Inertia::render('fee-rules/Index', [
            'filters' => [
                'q' => $filters['q'] ?? '',
                'category' => $filters['category'] ?? '',
                'scope' => $filters['scope'] ?? '',
                'calculation_type' => $filters['calculation_type'] ?? '',
                'status' => $filters['status'] ?? 'active',
            ],
            'feeRules' => $feeRules,
            'summary' => [
                'total_rules' => FeeRule::query()->count(),
                'active_rules' => FeeRule::query()->where('is_active', true)->count(),
                'mrc_rules' => FeeRule::query()->where('legacy_source_id', 'like', 'LEGAL-MRC-001%')->count(),
                'blocked_policy_count' => FeeRule::query()
                    ->whereNotNull('metadata')
                    ->get()
                    ->filter(fn (FeeRule $feeRule): bool => filled($feeRule->metadata['policy_boundaries'] ?? []))
                    ->count(),
            ],
            'categories' => $this->options(FeeRuleCategory::cases()),
            'scopes' => $this->options(FeeRuleScope::cases()),
            'calculationTypes' => $this->options(FeeRuleCalculationType::cases()),
        ]);
    }

    public function show(FeeRule $feeRule): Response
    {
        Gate::authorize(UserPermission::ViewFeeRules->value);

        $feeRule->load([
            'lineOfBusiness',
            'ranges' => fn ($query) => $query->orderBy('min_basis_cents'),
        ]);

        return Inertia::render('fee-rules/Show', [
            'feeRule' => [
                ...$this->feeRulePayload($feeRule),
                'ranges' => $feeRule->ranges->map(fn ($range): array => [
                    'id' => $range->id,
                    'min_basis_cents' => $range->min_basis_cents,
                    'max_basis_cents' => $range->max_basis_cents,
                    'amount_cents' => $range->amount_cents,
                    'rate_basis_points' => $range->rate_basis_points,
                ])->values()->all(),
            ],
            'scopeNote' => 'This detail page is read-only evidence. It exposes the persisted catalog rule, legal provenance, applicability, and unresolved policy boundaries without editing rates or inventing assessment behavior.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function feeRulePayload(FeeRule $feeRule): array
    {
        return [
            'id' => $feeRule->id,
            'code' => $feeRule->code,
            'name' => $feeRule->name,
            'category' => $feeRule->category->value,
            'scope' => $feeRule->scope->value,
            'calculation_type' => $feeRule->calculation_type->value,
            'basis' => $feeRule->basis,
            'amount_cents' => $feeRule->amount_cents,
            'rate_basis_points' => $feeRule->rate_basis_points,
            'effective_from' => $feeRule->effective_from?->toDateString(),
            'effective_until' => $feeRule->effective_until?->toDateString(),
            'is_active' => $feeRule->is_active,
            'legal_basis' => $feeRule->legal_basis,
            'legacy_source_id' => $feeRule->legacy_source_id,
            'line_of_business' => $feeRule->lineOfBusiness ? [
                'id' => $feeRule->lineOfBusiness->id,
                'code' => $feeRule->lineOfBusiness->code,
                'name' => $feeRule->lineOfBusiness->name,
            ] : null,
            'range_count' => (int) ($feeRule->ranges_count ?? $feeRule->ranges()->count()),
            'catalog_status' => $feeRule->metadata['catalog_status'] ?? null,
            'application_types' => $feeRule->metadata['application_types'] ?? null,
            'policy_boundaries' => $feeRule->metadata['policy_boundaries'] ?? [],
            'policy_note' => $feeRule->metadata['policy_note'] ?? null,
        ];
    }

    /**
     * @param  array<int, FeeRuleCategory|FeeRuleScope|FeeRuleCalculationType>  $cases
     * @return array<int, array{label: string, value: string}>
     */
    private function options(array $cases): array
    {
        return collect($cases)
            ->map(fn (FeeRuleCategory|FeeRuleScope|FeeRuleCalculationType $case): array => [
                'label' => str($case->value)->replace('_', ' ')->title()->toString(),
                'value' => $case->value,
            ])
            ->values()
            ->all();
    }
}
