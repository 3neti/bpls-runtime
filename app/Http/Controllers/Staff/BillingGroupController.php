<?php

namespace App\Http\Controllers\Staff;

use App\Actions\CreateBillingGroup;
use App\Actions\DescribeBillingGroupFinancialReadiness;
use App\Enums\BillingGroupEvidenceType;
use App\Enums\BillingGroupFieldType;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreBillingGroupRequest;
use App\Models\BillingGroup;
use App\Models\BillingGroupField;
use App\Models\BillingGroupReconciliation;
use App\Models\BillingGroupRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BillingGroupController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(UserPermission::ViewBillingGroups->value);

        $billingGroups = BillingGroup::query()
            ->withCount(['fields', 'records'])
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (BillingGroup $billingGroup): array => [
                'id' => $billingGroup->id,
                'name' => $billingGroup->name,
                'description' => $billingGroup->description,
                'acceptance_status' => $billingGroup->acceptance_status->value,
                'is_active' => $billingGroup->is_active,
                'fields_count' => $billingGroup->fields_count,
                'records_count' => $billingGroup->records_count,
            ]);

        return Inertia::render('billing-groups/Index', [
            'billingGroups' => $billingGroups,
            'fieldTypes' => collect(BillingGroupFieldType::cases())
                ->map(fn (BillingGroupFieldType $type): array => [
                    'value' => $type->value,
                    'label' => str($type->value)->headline()->toString(),
                ]),
            'can' => [
                'manage' => Gate::allows(UserPermission::ManageBillingGroups->value),
            ],
            'policyNote' => 'Billing groups are provisional non-permit Treasury configuration. Creating a definition does not accept it as a TOR module or authorize assessment, collection, receipt, or accounting treatment.',
        ]);
    }

    public function store(StoreBillingGroupRequest $request, CreateBillingGroup $createBillingGroup): RedirectResponse
    {
        $billingGroup = $createBillingGroup->handle($request->validatedForBillingGroup());

        return to_route('staff.billing-groups.show', $billingGroup);
    }

    public function show(BillingGroup $billingGroup, DescribeBillingGroupFinancialReadiness $describeFinancialReadiness): Response
    {
        Gate::authorize(UserPermission::ViewBillingGroups->value);
        Gate::authorize(UserPermission::ViewBillingGroupRecords->value);

        $billingGroup->load([
            'fields',
            'records' => fn ($query) => $query->with('createdBy')->latest(),
            'reconciliations' => fn ($query) => $query->with('recordedBy')->latest('version'),
        ]);
        $billingGroup->setRelation('currentReconciliation', $billingGroup->reconciliations->first());
        $billingGroup->records->each(
            fn (BillingGroupRecord $record): BillingGroupRecord => $record->setRelation('billingGroup', $billingGroup),
        );

        return Inertia::render('billing-groups/Show', [
            'billingGroup' => [
                'id' => $billingGroup->id,
                'name' => $billingGroup->name,
                'description' => $billingGroup->description,
                'acceptance_status' => $billingGroup->acceptance_status->value,
                'is_active' => $billingGroup->is_active,
                'fields' => $billingGroup->fields->map(fn (BillingGroupField $field): array => [
                    'id' => $field->id,
                    'key' => $field->key,
                    'name' => $field->name,
                    'field_type' => $field->field_type->value,
                    'is_required' => $field->is_required,
                    'is_unique' => $field->is_unique,
                    'options' => $field->options ?? [],
                    'placeholder' => $field->placeholder,
                    'default_value' => $field->default_value,
                ]),
                'records' => $billingGroup->records->map(fn (BillingGroupRecord $record): array => [
                    'id' => $record->id,
                    'draft_reference' => $record->draft_reference,
                    'status' => $record->status->value,
                    'description' => $record->description,
                    'record_date' => $record->record_date?->toDateString(),
                    'payor_name' => $record->payor_name,
                    'field_values' => $record->field_values ?? [],
                    'financial_readiness' => $describeFinancialReadiness->handle($record),
                    'created_by' => $record->createdBy->name,
                    'created_at' => $record->created_at?->toIso8601String(),
                ]),
                'reconciliations' => $billingGroup->reconciliations->map(fn (BillingGroupReconciliation $reconciliation): array => [
                    'id' => $reconciliation->id,
                    'version' => $reconciliation->version,
                    'evidence_type' => $reconciliation->evidence_type->value,
                    'evidence_reference' => $reconciliation->evidence_reference,
                    'source_excerpt' => $reconciliation->source_excerpt,
                    'operational_interpretation' => $reconciliation->operational_interpretation,
                    'unresolved_questions' => $reconciliation->unresolved_questions,
                    'reconciliation_status' => $reconciliation->reconciliation_status->value,
                    'execution_status' => $reconciliation->execution_status,
                    'execution_reason' => $reconciliation->execution_reason,
                    'recorded_by' => $reconciliation->recordedBy->name,
                    'created_at' => $reconciliation->created_at?->toIso8601String(),
                ]),
            ],
            'can' => [
                'create_record' => Gate::allows(UserPermission::CreateBillingGroupRecords->value),
                'record_reconciliation_evidence' => Gate::allows(UserPermission::RecordBillingGroupReconciliationEvidence->value),
            ],
            'evidenceTypes' => collect(BillingGroupEvidenceType::cases())->map(fn (BillingGroupEvidenceType $type): array => [
                'value' => $type->value,
                'label' => str($type->value)->headline()->toString(),
            ]),
            'policyNote' => 'Draft records are incomplete declarations only. Required and unique field readiness is not yet enforced, and no amount, liability, collection, receipt, or official transaction number is created.',
        ]);
    }
}
