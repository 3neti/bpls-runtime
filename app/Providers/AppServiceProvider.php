<?php

namespace App\Providers;

use App\Actions\RecordClassicLifecycleCeremonyEvent;
use App\Data\Application\ApplicationDataResolver;
use App\Data\Application\EloquentApplicationDataResolver;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ApplicationDataResolver::class, EloquentApplicationDataResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureClassicLifecycleObservation();
    }

    private function configureClassicLifecycleObservation(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                app(RecordClassicLifecycleCeremonyEvent::class)->forUser($event->user, 'logged_in', 'login.store');
            }
        });
        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                app(RecordClassicLifecycleCeremonyEvent::class)->forUser($event->user, 'logged_out', 'logout');
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        RateLimiter::for('stakeholder-preview', fn (Request $request): Limit => Limit::perMinute(240)->by($request->ip()));

        Gate::before(function (User $user): ?bool {
            if (! $user->hasActiveAccess()) {
                return false;
            }

            return $user->hasRole(UserRole::Admin) ? true : null;
        });

        foreach (UserPermission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => $user->hasPermission($permission));
        }
    }
}
