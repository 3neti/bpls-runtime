<?php

namespace App\Console\Commands;

use App\Actions\EnsureCitizenRole;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Throwable;

#[Signature('lifecycle:prepare-nelson-walkthrough
    {--run-id= : Stable walkthrough run reference}
    {--phase=all : prepare, browser, audit, or all}
    {--base-url=http://bpls-runtime.test : Browser base URL}')]
#[Description('Prepare local demo users and run the deterministic Nelson municipal workflow walkthrough.')]
class PrepareNelsonWalkthroughCommand extends Command
{
    public function handle(EnsureCitizenRole $ensureCitizenRole): int
    {
        try {
            $this->assertSafeEnvironment();
            $password = $this->runtimePassword();
            $operatorEmail = $this->runtimeEmail('NELSON_WALKTHROUGH_OPERATOR_EMAIL', 'nelson.walkthrough.operator@example.test');
            $approverEmail = $this->runtimeEmail('NELSON_WALKTHROUGH_APPROVER_EMAIL', 'nelson.walkthrough.treasurer@example.test');
            $citizenEmail = $this->runtimeEmail('NELSON_WALKTHROUGH_CITIZEN_EMAIL', 'nelson.walkthrough.citizen@example.test');
            $operator = $this->prepareOperator($operatorEmail, $password);
            $approver = $this->prepareUser(
                $approverEmail,
                'Nelson Walkthrough Municipal Treasurer',
                $password,
                Role::query()->findOrFail($operator->role_id),
            );
            $citizen = $this->prepareCitizen($citizenEmail, $password, $ensureCitizenRole);
            $this->configureScenario($operator, $approver, $citizen, $password);
            $runId = $this->runId();

            $exitCode = $this->call('lifecycle:scenario', [
                'scenario' => 'nelson_walkthrough',
                '--run-id' => $runId,
                '--phase' => (string) $this->option('phase'),
                '--base-url' => (string) $this->option('base-url'),
            ]);

            if ($exitCode !== self::SUCCESS) {
                return self::FAILURE;
            }

            $this->newLine();
            $this->line('Walkthrough users prepared from runtime credentials.');
            $this->line('Citizen: '.$citizen->email);
            $this->line('Operator: '.$operator->email);
            $this->line('Municipal Treasurer: '.$approver->email);
            $this->line('Password: supplied by NELSON_WALKTHROUGH_PASSWORD (not displayed)');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function assertSafeEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Nelson walkthrough user preparation is allowed only in local or testing.');
        }
    }

    private function runtimePassword(): string
    {
        $password = getenv('NELSON_WALKTHROUGH_PASSWORD');

        if (! is_string($password) || mb_strlen($password) < 16) {
            throw new RuntimeException('NELSON_WALKTHROUGH_PASSWORD must be supplied at runtime and contain at least 16 characters.');
        }

        return $password;
    }

    private function runtimeEmail(string $key, string $default): string
    {
        $value = getenv($key);
        $email = is_string($value) && $value !== '' ? $value : $default;

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException("{$key} must be a valid email address.");
        }

        return $email;
    }

    private function prepareOperator(string $email, string $password): User
    {
        $permissions = collect(UserPermission::cases())
            ->map(fn (UserPermission $permission): Permission => Permission::query()->firstOrCreate(
                ['code' => $permission->value],
                [
                    'name' => str($permission->value)->replace(['.', '_'], ' ')->title()->toString(),
                    'description' => null,
                ],
            ));
        $adminRole = Role::query()->firstOrCreate(
            ['code' => UserRole::Admin->value],
            [
                'name' => 'Admin',
                'description' => 'Local administrative scenario role.',
            ],
        );
        $adminRole->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());

        return $this->prepareUser($email, 'Nelson Walkthrough Operator', $password, $adminRole);
    }

    private function prepareCitizen(string $email, string $password, EnsureCitizenRole $ensureCitizenRole): User
    {
        return $this->prepareUser($email, 'Nelson Walkthrough Citizen', $password, $ensureCitizenRole->handle());
    }

    private function prepareUser(string $email, string $name, string $password, Role $role): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'role_id' => $role->id,
            'password' => Hash::make($password),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return $user->refresh();
    }

    private function configureScenario(User $operator, User $approver, User $citizen, string $password): void
    {
        config()->set('lifecycle_scenarios.actors.citizen_applicant.email', $citizen->email);
        config()->set('lifecycle_scenarios.actors.primary_operator.email', $operator->email);
        config()->set('lifecycle_scenarios.actors.assessment_approver.email', $approver->email);
        config()->set('lifecycle_scenarios.actors.sample_recipient.email', $operator->email);

        $this->setProcessEnvironment('LIFECYCLE_BROWSER_EMAIL', $citizen->email);
        $this->setProcessEnvironment('LIFECYCLE_BROWSER_PASSWORD', $password);
        $this->setProcessEnvironment('LIFECYCLE_BROWSER_OPERATOR_EMAIL', $operator->email);
        $this->setProcessEnvironment('LIFECYCLE_BROWSER_OPERATOR_PASSWORD', $password);
        $this->setProcessEnvironment('LIFECYCLE_ASSESSMENT_APPROVER_EMAIL', $approver->email);
    }

    private function setProcessEnvironment(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function runId(): string
    {
        $runId = $this->option('run-id');
        $runId = is_string($runId) && $runId !== ''
            ? $runId
            : 'nelson-walkthrough-'.now()->format('Ymd-His');

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/', $runId) !== 1) {
            throw new RuntimeException('The walkthrough run ID must be a stable filesystem-safe reference.');
        }

        return $runId;
    }
}
