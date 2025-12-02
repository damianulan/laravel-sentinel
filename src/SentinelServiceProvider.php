<?php

namespace Sentinel;

use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Sentinel\Console\Commands\AssignRca;
use Sentinel\Console\Commands\Generators\MakePermissionsLibCommand;
use Sentinel\Console\Commands\Generators\MakeRolesLibCommand;
use Sentinel\Models\Permission;
use Sentinel\Traits\HasRolesAndPermissions;

/**
 * @author Damian Ułan <damian.ulan@protonmail.com>
 * @copyright 2025 damianulan
 * @license MIT
 */
class SentinelServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sentinel.php', 'sentinel');
    }

    /**
     * When this method is apply we have all laravel providers and methods available
     */
    public function boot(): void
    {

        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'sentinel');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ]);

        $this->publishes([
            __DIR__ . '/../lang' => $this->app->langPath('vendor/sentinel'),
        ], 'sentinel-langs');

        $this->publishes([
            __DIR__ . '/../config/sentinel.php' => config_path('sentinel.php'),
        ], 'sentinel-config');

        $this->publishes([
            __DIR__ . '/../stubs' => base_path('stubs'),
            __DIR__ . '/../config/sentinel.php' => config_path('sentinel.php'),
        ], 'sentinel');

        $this->registerCommands();
        $this->bootRolesAndPermissions();
    }

    public function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AssignRca::class,
                MakePermissionsLibCommand::class,
                MakeRolesLibCommand::class,
            ]);
        }
    }

    private function bootRolesAndPermissions(): void
    {
        Blade::if('role', function ($role) {
            $user = Auth::user();
            if ($user && class_uses_trait(HasRolesAndPermissions::class, $user::class)) {
                return $user->hasRole($role);
            }

            return false;
        });

        Blade::if('admin', function () {
            $user = Auth::user();
            if ($user && class_uses_trait(HasRolesAndPermissions::class, $user::class)) {
                return $user->isAdmin();
            }

            return false;
        });

        try {
            Permission::get()->map(function ($permission): void {
                Gate::define($permission->slug, function ($user, $context = null) use ($permission) {
                    if ($user && class_uses_trait(HasRolesAndPermissions::class, $user::class)) {
                        return $user->hasPermissionTo($permission, $context);
                    }

                    return false;
                });
            });
        } catch (Exception $e) {
            Log::error(static::class . ' failed fetching permission: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }

        Gate::before(function ($user, string $ability) {
            if ($user && class_uses_trait(HasRolesAndPermissions::class, $user::class)) {
                if ($user->isRoot()) {
                    return true;
                }
            }
        });
    }
}
