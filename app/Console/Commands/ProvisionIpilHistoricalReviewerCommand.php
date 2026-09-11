<?php

namespace App\Console\Commands;

use App\Enums\UserPermission;
use App\Http\Middleware\RestrictIpilHistoricalUat;
use App\Models\User;
use App\Support\IpilRescue\Gate8cExecutionAuthorization;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

#[Signature('ipil:uat:reviewer {--confirm-environment= : Exact isolated historical environment ID}')]
#[Description('Provision the explicitly configured private historical reviewer using ordinary BPLS authentication.')]
final class ProvisionIpilHistoricalReviewerCommand extends Command
{
    public function handle(): int
    {
        $email = strtolower((string) config('ipil_historical_uat.reviewer_email'));
        $password = (string) config('ipil_historical_uat.bootstrap_password');
        if (! app()->environment('historical-uat')
            || config('ipil_historical_uat.enabled') !== true
            || config('stakeholder_preview.mode') !== false
            || config('ipil_historical_uat.environment_id') !== RestrictIpilHistoricalUat::EnvironmentId
            || $this->option('confirm-environment') !== RestrictIpilHistoricalUat::EnvironmentId
            || DB::connection()->getDriverName() !== 'pgsql'
            || DB::connection()->getDatabaseName() !== Gate8cExecutionAuthorization::Database
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($password) < 40) {
            $this->error('Reviewer provisioning refused: explicit private historical configuration is required.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($email, $password): void {
            $user = User::query()->where('email', $email)->first();
            if ($user === null) {
                $user = User::query()->create([
                    'name' => 'Restricted Historical Reviewer', 'email' => $email,
                    'password' => $password, 'access_status' => 'active',
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            foreach ([UserPermission::AccessStaff, UserPermission::ViewPermitApplications] as $permission) {
                $user->givePermissionTo(Permission::findOrCreate($permission->value, 'web'));
            }
        });
        $this->info('Authorized historical reviewer provisioned; identity and credential omitted.');

        return self::SUCCESS;
    }
}
