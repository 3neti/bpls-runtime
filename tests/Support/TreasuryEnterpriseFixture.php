<?php

use App\Actions\BuildBploRoutingTask;
use App\Assessment\ProvisionalTreasuryEnterpriseSchedule;
use App\Enums\UserPermission;
use App\Models\BploRoutingDetermination;
use App\Models\BploRoutingWork;
use App\Models\PaperlessPaymentOrder;
use App\Models\PaperlessPaymentOrderLine;
use App\Models\PermitApplication;
use App\Models\PermitApplicationDeclaration;
use Database\Seeders\MunicipalFeeCatalogSeeder;

function enterpriseUatFixture(): array
{
    config(['app.url' => config('treasury_enterprise.workflow_url'), 'treasury_enterprise.provisional_uat_enabled' => true, 'treasury_enterprise.provisional_uat_context' => 'workflow_uat', 'stakeholder_preview.mode' => true,
        'stakeholder_preview.production_migration_enabled' => false, 'stakeholder_preview.production_integrations' => 'disabled']);
    test()->seed(MunicipalFeeCatalogSeeder::class);
    $application = PermitApplication::factory()->create(['application_year' => 2026, 'type' => 'new',
        'metadata' => ['nelson_reconciliation_v1' => ['commissioned_path' => true]]]);
    PermitApplicationDeclaration::factory()->for($application)->create(['snapshot' => ['establishment' => ['male_employees' => 1, 'female_employees' => 0, 'business_area_square_meters' => '12.00']]]);
    $actor = userWithPermissions([UserPermission::AccessStaff, UserPermission::CorrectEvaluationLinesOfBusiness]);
    $routing = BploRoutingDetermination::factory()->create(['permit_application_id' => $application->id]);
    foreach (['assessor' => 10000, 'engineering' => 15000, 'health' => 30000, 'menro' => 250000] as $office => $amount) {
        $work = BploRoutingWork::factory()->create(['bplo_routing_determination_id' => $routing->id, 'office_code' => $office]);
        $order = PaperlessPaymentOrder::factory()->create(['bplo_routing_work_id' => $work->id, 'permit_application_id' => $application->id, 'total_amount_cents' => $amount]);
        PaperlessPaymentOrderLine::factory()->create(['paperless_payment_order_id' => $order->id, 'amount_cents' => $amount]);
    }
    $lob = collect(app(BuildBploRoutingTask::class)->handle($application, $actor)->financial_editor['line_of_business_options'])->firstWhere('code', 'LOB-3A9A93CA46967768');
    $mayor = collect($lob['default_items'])->firstWhere('code', ProvisionalTreasuryEnterpriseSchedule::FeeCode);
    $selection = ['line_of_business_id' => $lob['id'], 'enterprise_classification' => 'Small',
        'enterprise_schedule_fingerprint' => $mayor['enterprise_schedule']['fingerprint'],
        'items' => collect($lob['default_items'])->map(fn ($item) => ['fee_rule_id' => $item['fee_rule_id'], 'amount_cents' => $item['code'] === ProvisionalTreasuryEnterpriseSchedule::FeeCode ? 100000 : $item['amount_cents']])->all()];

    return [$application, $actor, $selection, $mayor];
}
