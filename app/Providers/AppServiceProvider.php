<?php

namespace App\Providers;

use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Repositories\SettingRepository;
use App\Models\Session;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\UserUnitKerja;
use App\Observers\SessionObserver;
use App\Observers\UnitKerjaObserver;
use App\Observers\UserApplicationRoleObserver;
use App\Observers\UserObserver;
use App\Observers\UserUnitKerjaObserver;
use App\Observers\ApplicationObserver;
use App\Services\AppRegistry;
use App\Services\SettingService;
use App\Services\Contracts\AppRegistryContract;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AppRegistryContract::class, AppRegistry::class);
        $this->app->singleton(SettingRepositoryInterface::class, SettingRepository::class);
        $this->app->singleton(SettingService::class, function ($app) {
            return new SettingService($app->make(SettingRepositoryInterface::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Gate $gate): void
    {
        // Set filesystem disk dynamically based on environment
        // Production uses S3, other environments use local storage
        if (! app()->environment('production')) {
            config(['filesystems.default' => 'local']);
        }

        $gate->define('viewPulse', function (User $user) {
            return true;
        });

        View::composer('*', function ($view): void {
            $request = app(Request::class);
            $isAuthenticated = auth()->check();
            $isAuthRoute = $request->routeIs('login')
                || $request->routeIs('register')
                || $request->is('login')
                || $request->is('register');

            $view->with('shouldDisableZoom', $isAuthenticated || $isAuthRoute);
        });

        User::observe(UserObserver::class);
        Session::observe(SessionObserver::class);
        UnitKerja::observe(UnitKerjaObserver::class);
        UserUnitKerja::observe(UserUnitKerjaObserver::class);
        \App\Domain\Iam\Models\UserApplicationRole::observe(UserApplicationRoleObserver::class);
        \App\Models\UserAccessProfile::observe(\App\Observers\UserAccessProfileObserver::class);
        \App\Domain\Iam\Models\Application::observe(ApplicationObserver::class);
    }
}
