<?php

namespace App\Actions;

use App\Enums\PermitApplicationStatus;
use App\Models\PermitApplication;
use Closure;
use DomainException;
use LogicException;

final class PermitApplicationStatusMutation
{
    private static int $privilegeDepth = 0;

    /** @param array<string, mixed> $attributes */
    public function persistStatusConsequence(
        PermitApplication $permitApplication,
        PermitApplicationStatus $status,
        array $attributes = [],
    ): PermitApplication {
        if (in_array($status, [PermitApplicationStatus::HistoricalEvidence, PermitApplicationStatus::Released], true)) {
            throw new LogicException("Status [{$status->value}] is not an operational PermitApplication transition target.");
        }

        return self::whilePrivileged(function () use ($permitApplication, $status, $attributes): PermitApplication {
            $permitApplication->forceFill([
                ...$attributes,
                'status' => $status,
            ])->save();

            return $permitApplication;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function createHistoricalMigrationProjection(array $attributes): PermitApplication
    {
        $status = $attributes['status'] ?? null;
        if ($status instanceof PermitApplicationStatus) {
            $status = $status->value;
        }

        $allowedMigrationStatuses = [
            PermitApplicationStatus::Draft->value,
            PermitApplicationStatus::Assessment->value,
            PermitApplicationStatus::Approval->value,
            PermitApplicationStatus::PendingPayment->value,
            PermitApplicationStatus::HistoricalEvidence->value,
        ];

        if (! in_array($status, $allowedMigrationStatuses, true)) {
            throw new LogicException('Historical PermitApplication migration may create only an accepted preserved scalar status.');
        }

        if (data_get($attributes, 'metadata.migration.schema_version') !== 'bpls.legacy-application-migration.v1') {
            throw new LogicException('Historical PermitApplication migration requires explicit migration provenance.');
        }

        if ($status === PermitApplicationStatus::HistoricalEvidence->value
            && data_get($attributes, 'metadata.historical_semantics.operationally_eligible') !== false) {
            throw new LogicException('Historical PermitApplication creation must remain explicitly non-operational.');
        }

        return self::whilePrivileged(function () use ($attributes): PermitApplication {
            $permitApplication = new PermitApplication;
            $permitApplication->fill($attributes);
            $permitApplication->save();

            return $permitApplication;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function createProvisionalUatFixture(array $attributes): PermitApplication
    {
        if (($attributes['status'] ?? null) !== PermitApplicationStatus::Assessment) {
            throw new LogicException('A provisional UAT PermitApplication must begin in the accepted assessment state.');
        }

        if (data_get($attributes, 'metadata.business_permit_evaluation.semantic_classification') !== 'provisional_uat') {
            throw new LogicException('A provisional UAT PermitApplication requires its explicit semantic classification.');
        }

        return self::whilePrivileged(function () use ($attributes): PermitApplication {
            $permitApplication = new PermitApplication;
            $permitApplication->fill($attributes);
            $permitApplication->save();

            return $permitApplication;
        });
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public static function forFactoryFixture(Closure $callback): mixed
    {
        if (! app()->environment('testing')) {
            throw new LogicException('PermitApplication factory status privilege is available only in the testing environment.');
        }

        return self::whilePrivileged($callback);
    }

    public static function assertPrivileged(): void
    {
        if (self::$privilegeDepth === 0) {
            throw new DomainException('PermitApplication status may change only as a consequence of an authorized domain action.');
        }
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    private static function whilePrivileged(Closure $callback): mixed
    {
        self::$privilegeDepth++;

        try {
            return $callback();
        } finally {
            self::$privilegeDepth--;
        }
    }
}
