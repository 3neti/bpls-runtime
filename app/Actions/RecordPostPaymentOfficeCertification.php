<?php

namespace App\Actions;

use App\Models\LifecycleCleanroomRun;
use App\Models\PostPaymentOfficeCertification;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordPostPaymentOfficeCertification
{
    public function __construct(private readonly AuthorizeRoutedOfficeActor $authorizeRoutedOfficeActor) {}

    public function handle(
        PostPaymentOfficeCertification $certification,
        User $actor,
        string $result = 'certified',
        ?string $remarks = null,
    ): PostPaymentOfficeCertification {
        return DB::transaction(function () use ($certification, $actor, $result, $remarks): PostPaymentOfficeCertification {
            $certification = PostPaymentOfficeCertification::query()->whereKey($certification)->lockForUpdate()->firstOrFail();
            $certification->load(['permitApplication', 'receipt']);

            if (! in_array($result, ['certified', 'returned'], true)) {
                throw new DomainException('A post-payment office result must be certified or returned.');
            }
            if ($certification->semantic_classification !== 'synthetic_only' || $certification->production_authority) {
                throw new DomainException('This action cannot assert production office-certification authority.');
            }
            $runId = data_get($certification->permitApplication->metadata, 'lifecycle_cleanroom.run_id');
            $run = is_string($runId) ? LifecycleCleanroomRun::query()->where('public_id', $runId)->first() : null;
            if (! $run instanceof LifecycleCleanroomRun) {
                throw new DomainException('Post-payment certification requires its owning cleanroom run.');
            }
            $this->authorizeRoutedOfficeActor->handle($certification->permitApplication, $certification->office_code, $actor);
            $routedWorkIds = $certification->permitApplication->bploRoutingDetermination?->works()
                ->where('office_code', $certification->office_code)
                ->pluck('id')->map(fn (mixed $id): int => (int) $id)->sort()->values()->all() ?? [];
            $certificationWorkIds = collect($certification->routing_work_ids)->sort()->values()->all();
            if ($routedWorkIds === [] || $routedWorkIds !== $certificationWorkIds) {
                throw new DomainException('Post-payment certification office must match its exact routed work.');
            }

            $evidence = $certification->evidence;
            $evidence['result'] = [
                'receipt_id' => $certification->receipt_id,
                'receipt_number' => $certification->receipt->receipt_number,
                'reviewed' => true,
                'synthetic_only' => true,
                'real_office_certification' => false,
            ];
            $certification->forceFill([
                'certified_by_id' => $actor->id,
                'status' => $result === 'certified' ? 'completed' : 'returned',
                'result' => $result,
                'remarks' => filled($remarks) ? Str::squish($remarks) : null,
                'evidence' => $evidence,
                'certified_at' => now(),
            ])->save();

            return $certification->load(['certifiedBy', 'receipt']);
        }, 3);
    }
}
