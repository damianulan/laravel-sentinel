<?php

declare(strict_types=1);

namespace Sentinel;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Sentinel\Cache\PermissionCache;
use Sentinel\Console\Commands\AssignRca;
use Sentinel\Helpers\SentinelHelper;
use Sentinel\Http\Middleware\PermissionMiddleware;
use Sentinel\Http\Middleware\RoleMiddleware;
use Sentinel\Registry\RegistryDiscovery;
use Sentinel\Registry\RegistryRepository;

final class SentinelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sentinel.php', 'sentinel');

        $this->app->singleton(RegistryRepository::class, fn (): RegistryRepository => new RegistryRepository(
            new RegistryDiscovery(
                $this->app->basePath('vendor/composer/autoload_classmap.php'),
                $this->app->basePath(),
            ),
        ));

        $this->app->singleton(PermissionCache::class, fn (): PermissionCache => new PermissionCache(
            store: Cache::store(SentinelHelper::getSentinelCacheStore()),
            prefix: SentinelHelper::getSentinelCacheKey(),
            ttl: SentinelHelper::getSentinelCacheTtl(),
            enabled: SentinelHelper::isSentinelCacheEnabled(),
        ));
    }

    public function boot(Router $router): void
    {
        $migrations = $this->migrationPublishers();

        $this->publishesMigrations($migrations, 'sentinel-migrations');
        $this->publishes([
            __DIR__ . '/../config/sentinel.php' => config_path('sentinel.php'),
        ], 'sentinel-config');
        $this->publishes([
            __DIR__ . '/../config/sentinel.php' => config_path('sentinel.php'),
            ...$migrations,
        ], 'sentinel');

        if ($this->app->runningInConsole()) {
            $this->commands([AssignRca::class]);
        }

        $router->aliasMiddleware('sentinel.role', RoleMiddleware::class);
        $router->aliasMiddleware('sentinel.permission', PermissionMiddleware::class);

        $this->registerBladeConditions();
        $this->registerGateHook();
    }

    /** @return array<string, string> */
    private function migrationPublishers(): array
    {
        $timestamp = now()->startOfSecond();
        $migrations = [
            'create_roles_table.php',
            'create_permissions_table.php',
            'create_has_permissions_table.php',
            'create_roles_permissions_table.php',
            'create_has_roles_table.php',
        ];

        $publishers = [];

        foreach ($migrations as $offset => $migration) {
            $publishers[__DIR__ . "/../database/migrations/{$migration}.stub"] = database_path(sprintf(
                'migrations/%s_%s',
                $timestamp->copy()->addSeconds($offset)->format('Y_m_d_His'),
                $migration,
            ));
        }

        return $publishers;
    }

    private function registerBladeConditions(): void
    {
        Blade::if('role', function (mixed $role): bool {
            $user = Auth::user();

            return null !== $user
                && method_exists($user, 'hasRole')
                && (bool) call_user_func([$user, 'hasRole'], $role);
        });

        Blade::if('admin', function (): bool {
            $user = Auth::user();

            return null !== $user
                && method_exists($user, 'isAdmin')
                && (bool) call_user_func([$user, 'isAdmin']);
        });

        Blade::if('root', function (): bool {
            $user = Auth::user();

            return null !== $user
                && method_exists($user, 'isRoot')
                && (bool) call_user_func([$user, 'isRoot']);
        });
    }

    private function registerGateHook(): void
    {
        $this->callAfterResolving(Gate::class, function (Gate $gate): void {
            $gate->before(function (Authorizable $user, string $ability, array $arguments = []): ?bool {
                if ( ! method_exists($user, 'hasPermissionTo')) {
                    return null;
                }

                if ( ! array_key_exists($ability, app(RegistryRepository::class)->permissions())) {
                    return null;
                }

                $context = null;

                foreach ($arguments as $argument) {
                    if ($argument instanceof Model) {
                        $context = $argument;

                        break;
                    }
                }

                return call_user_func([$user, 'hasPermissionTo'], $ability, $context)
                    ? true
                    : null;
            });
        });
    }
}
