<?php

namespace App\Actions;

use App\Enums\PermitApplicationStatus;
use App\Models\PermitApplicationDocument;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class RemoveCitizenPermitApplicationDocument
{
    public function handle(PermitApplicationDocument $document, User $actor): void
    {
        DB::transaction(function () use ($document, $actor): void {
            $document = PermitApplicationDocument::query()->with(['permitApplication', 'media'])->lockForUpdate()->findOrFail($document->id);
            if ($document->permitApplication->submitted_by_id !== $actor->id
                || $document->permitApplication->status !== PermitApplicationStatus::Draft) {
                throw new DomainException('Applicant documents may be removed only from the applicant\'s draft.');
            }

            $document->forceFill(['removed_at' => now()])->save();
            $document->media?->delete();
        });
    }
}
