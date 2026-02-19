<?php

namespace Sentinel;

use Exception;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Facades\Gate as GateFacade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Sentinel\Console\Commands\AssignRca;
use Sentinel\Console\Commands\Generators\MakePermissionsLibCommand;
use Sentinel\Console\Commands\Generators\MakeRolesLibCommand;
use Sentinel\Models\Permission;
use Sentinel\Traits\HasRolesAndPermissions;
use Illuminate\Contracts\Foundation\Application;

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

        $this->publishes($this->migrationPublishers(), 'sentinel-migrations');

        $this->publishes([
            __DIR__ . '/../lang' => $this->app->langPath('vendor/sentinel'),
        ], 'sentinel-langs');

        $this->publishes([
            __DIR__ . '/../config/sentinel.php' => config_path('sentinel.php'),
        ], 'sentinel-config');

        $this->publishes(array_merge([
            __DIR__ . '/../stubs' => base_path('stubs'),
            __DIR__ . '/../config/sentinel.php' => config_path('sentinel.php'),
        ], $this->migrationPublishers()), 'sentinel');

        $this->registerCommands();
        $this->bootRolesAndPermissions();
    }

    public function registerCommands(): void
    {
        $this->commands([
            AssignRca::class,
            MakePermissionsLibCommand::class,
            MakeRolesLibCommand::class,
        ]);
    }

    private function migrationPublishers(): array
    {
        $date = date('Y_m_d', time());
        $time = (int) date('His', time());

        return [
            __DIR__ . '/../database/migrations/create_roles_table.php.stub' => database_path('migrations/' . $date . '_' . $time . '_create_roles_table.php'),
            __DIR__ . '/../database/migrations/create_permissions_table.php.stub' => database_path('migrations/' . $date . '_' . $time + 1 . '_create_permissions_table.php'),
            __DIR__ . '/../database/migrations/create_has_permissions_table.php.stub' => database_path('migrations/' . $date . '_' . $time + 2 . '_create_has_permissions_table.php'),
            __DIR__ . '/../database/migrations/create_roles_permissions_table.php.stub' => database_path('migrations/' . $date . '_' . $time + 3 . '_create_roles_permissions_table.php'),
            __DIR__ . '/../database/migrations/create_has_roles_table.php.stub' => database_path('migrations/' . $date . '_' . $time + 4 . '_create_has_roles_table.php'),
        ];
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

        Blade::if('root', fn () => Auth::user()->hasRole(config('sentinel.root')));

        $this->callAfterResolving(Gate::class, function (Gate $gate, Application $app) {
            $this->registerPermission($gate);
        });
    }

    private function registerPermission(Gate $gate): void
    {
        $gate->before(function (Authorizable $user, string $ability, array &$args = []) {
            $context = null;
            foreach($args as $arg){
                if(!$context && $arg instanceof Model){
                    $context = $arg;
                }
            }

            if (method_exists($user, 'isRoot')) {
                if ($user->isRoot()) {
                    return true;
                }
            }
            if (method_exists($user, 'hasPermissionTo')) {
                return $user->hasPermissionTo($ability, $context) ?: null;
            }
        });

    }
}
