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

    public function __construct(
        private readonly AuthorizeRoutedOfficeActor $authorizeRoutedOfficeActor,
        private readonly CanonicalFinancialFingerprint $fingerprint,
        private readonly ProvisionalMenroFeeDeterminationProposal $proposal,
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
                ...$this->proposal->facts($application),
                'permit_application_id' => $application->id,
                'office_code' => self::OfficeCode,
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
        $expected = $this->proposal->facts($application);
        if ($expected === []) {
            throw new LogicException('This application is not eligible for a provisional MENRO determination.');
        }
        foreach ($expected as $key => $value) {
            if (($facts[$key] ?? null) !== $value) {
                throw new LogicException("The MENRO determination field [{$key}] does not match the authorized evidence.");
            }
        }
    }
}
