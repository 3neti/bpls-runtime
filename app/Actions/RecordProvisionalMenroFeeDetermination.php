<?php

namespace App\Actions;

use App\Assessment\Price\CanonicalFinancialFingerprint;
use App\Models\BploRoutingWork;
use App\Models\MenroFeeDetermination;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

final class RecordProvisionalMenroFeeDetermination
{
    public const OfficeCode = 'menro';
    public const FeeRuleId = 176;
    public const FeeCode = 'IPIL-LEGACY-98CDCAD9D28055FB';
    public const Reason = 'Provisional Gate 10 synthetic-UAT determination pending Ipil municipal confirmation';

    public function __construct(
        private readonly AuthorizeRoutedOfficeActor $authorizeRoutedOfficeActor,
        private readonly CanonicalFinancialFingerprint $fingerprint,
    ) {}

    /** @param array<string, mixed> $facts */
    public function handle(PermitApplication $application, User $actor, array $facts): MenroFeeDetermination
    {
        return DB::transaction(function () use ($application, $actor, $facts): MenroFeeDetermination {
            $this->assertExactFacts($application, $facts);
            $work = BploRoutingWork::query()
                ->where('office_code', self::OfficeCode)
                ->whereHas('determination', fn ($query) => $query->where('permit_application_id', $application->id))
                ->first();
            if (! $work instanceof BploRoutingWork) {
                throw new LogicException('MENRO routing work is not available for this application.');
            }
            $this->authorizeRoutedOfficeActor->handle(
                $application,
                self::OfficeCode,
                $actor,
                data_get($work->context_snapshot, 'authorized_actor_id'),
            );

            $canonical = [
                'permit_application_id' => $application->id,
                'office_code' => self::OfficeCode,
                'scope' => 'application',
                'fee_rule_id' => self::FeeRuleId,
                'code' => self::FeeCode,
                'basis' => 'business_area_square_meters',
                'application_area_square_meters' => 12,
                'calculation_basis_centi_square_meters' => 1200,
                'operative_range_min_centi_square_meters' => 1100,
                'operative_range_max_centi_square_meters' => 1600,
                'amount_minor' => 250000,
                'schedule_version' => 'ipil-municipal-fees-v1',
                'source_evidence' => 'LIVE-APP-001',
                'classification' => 'PROVISIONAL_UAT_ONLY',
                'production_authority' => false,
                'reason' => self::Reason,
                'actor_id' => $actor->id,
            ];
            $hash = $this->fingerprint->hash($canonical);
            $existing = MenroFeeDetermination::query()
                ->where('permit_application_id', $application->id)
                ->where('office_code', self::OfficeCode)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof MenroFeeDetermination) {
                if ($existing->fingerprint !== $hash) {
                    throw new LogicException('A different MENRO determination is already recorded for this application.');
                }

                return $existing;
            }

            $now = Carbon::now();

            return MenroFeeDetermination::create([
                ...$canonical,
                'determined_at' => $now,
                'fingerprint' => $hash,
            ]);
        });
    }

    /** @param array<string, mixed> $facts */
    private function assertExactFacts(PermitApplication $application, array $facts): void
    {
        $expected = [
            'scope' => 'application',
            'fee_rule_id' => self::FeeRuleId,
            'code' => self::FeeCode,
            'basis' => 'business_area_square_meters',
            'application_area_square_meters' => 12,
            'calculation_basis_centi_square_meters' => 1200,
            'operative_range_min_centi_square_meters' => 1100,
            'operative_range_max_centi_square_meters' => 1600,
            'amount_minor' => 250000,
            'schedule_version' => 'ipil-municipal-fees-v1',
            'source_evidence' => 'LIVE-APP-001',
            'classification' => 'PROVISIONAL_UAT_ONLY',
            'production_authority' => false,
            'reason' => self::Reason,
        ];
        if ($application->id !== 3 || $application->application_year !== 2026 || $application->type->value !== 'new') {
            throw new LogicException('This provisional MENRO determination is authorized only for Application 3.');
        }
        foreach ($expected as $key => $value) {
            if (($facts[$key] ?? null) !== $value) {
                throw new LogicException("The MENRO determination field [{$key}] does not match the authorized evidence.");
            }
        }
    }
}
