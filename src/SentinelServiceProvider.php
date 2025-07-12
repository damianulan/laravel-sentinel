<?php

namespace Sentinel;

use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Support\Facades\Blade;
use Sentinel\Console\Commands\AssignRca;
use Sentinel\Console\Commands\Generators\MakePermissionsLibCommand;
use Sentinel\Console\Commands\Generators\MakeRolesLibCommand;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @author Damian Ułan <damian.ulan@protonmail.com>
 * @copyright 2025 damianulan
 * @package Sentinel
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

        $this->loadMigrationsFrom([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ]);

        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ]);

        $this->publishes([
            __DIR__ . '/../lang'                   => $this->app->langPath('vendor/sentinel'),
        ], 'sentinel-langs');

        $this->publishes([
            __DIR__ . '/../config/sentinel.php'      => config_path('sentinel.php'),
        ], 'sentinel-config');

        $this->publishes([
            __DIR__ . '/../stubs'                  => base_path('stubs'),
            __DIR__ . '/../config/sentinel.php'      => config_path('sentinel.php'),
        ], 'sentinel');

        $this->registerBladeDirectives();
        $this->registerCommands();
        $this->bootRolesAndPermissions();
    }

    private function bootRolesAndPermissions(): void
    {
        Blade::if('role', function ($role) {
            $user = Auth::user();
            if ($user && $user instanceof \Sentinel\Traits\HasRolesAndPermissions) {
                return Auth::user()->hasRole($role);
            }
            return false;
        });

        try {
            Permission::get()->map(function ($permission) {
                Gate::define($permission->slug, function ($user, $context = null) use ($permission) {
                    return $user->hasPermissionTo($permission, $context);
                });
            });
        } catch (\Exception $e) {
            Log::error(static::class . ' failed fetching permission: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    public function registerBladeDirectives(): void {}

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
}
