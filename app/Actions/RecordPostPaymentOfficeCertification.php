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
            if (! $run instanceof LifecycleCleanroomRun
                || data_get($run->actor_manifest, 'actors.'.$certification->office_code.'.user_id') !== $actor->id) {
                throw new DomainException('Only the routed cleanroom office actor may record this certification.');
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
