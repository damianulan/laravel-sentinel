<?php

namespace Sentinel\Config;

use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use Sentinel\Config\Warden\PermissionWarden;
use Sentinel\Config\Warden\RoleWarden;
use Sentinel\Exceptions\PermissionWardenException;
use Sentinel\Exceptions\RoleWardenException;

class SentinelManager
{
    const CACHES = [
        'rolesLib',
        'permissionsLib',
    ];

    public static function getRolesLibNamespace(): ?string
    {
        $value = self::getCache('rolesLib');

        if (empty($value)) {
            $parentClass = RoleWarden::class;
            // Get all declared classes from Composer's autoloader
            $classMap = require base_path('vendor/composer/autoload_classmap.php');
            $classMap = array_filter($classMap, fn ($key) => str_contains($key, 'App\\Warden'), ARRAY_FILTER_USE_KEY);

            foreach (array_keys($classMap) as $class) {
                if ( ! class_exists($class)) {
                    continue;
                }

                try {
                    $reflection = new ReflectionClass($class);

                    if (
                        $reflection->isSubclassOf($parentClass) &&
                        ! $reflection->isAbstract()
                    ) {
                        $value = $class;
                    }
                } catch (ReflectionException $e) {
                    // Skip problematic classes
                }
            }
            self::putCache('rolesLib', $value);
        }

        return $value;
    }

    public static function getPermissionsLibNamespace(): ?string
    {
        $value = self::getCache('permissionsLib');

        if (empty($value)) {
            $parentClass = PermissionWarden::class;
            // Get all declared classes from Composer's autoloader
            $classMap = require base_path('vendor/composer/autoload_classmap.php');
            $classMap = array_filter($classMap, fn ($key) => str_contains($key, 'App\\Warden'), ARRAY_FILTER_USE_KEY);

            foreach (array_keys($classMap) as $class) {
                if ( ! class_exists($class)) {
                    continue;
                }

                try {
                    $reflection = new ReflectionClass($class);

                    if (
                        $reflection->isSubclassOf($parentClass) &&
                        ! $reflection->isAbstract()
                    ) {
                        $value = $class;
                    }
                } catch (ReflectionException $e) {
                    // Skip problematic classes
                }
            }
            self::putCache('permissionsLib', $value);
        }

        return $value;
    }

    public static function getRolesLib(): RoleWarden
    {
        $value = self::getRolesLibNamespace();
        if (empty($value)) {
            throw new RoleWardenException();
        }

        return new $value();
    }

    public static function getPermissionsLib(): PermissionWarden
    {
        $value = self::getPermissionsLibNamespace();
        if (empty($value)) {
            throw new PermissionWardenException();
        }

        return new $value();
    }

    public static function putCache(string $key, $value): void
    {
        if (in_array($key, self::CACHES)) {
            self::getCacheDriver()->put(config('sentinel.cache.prefix') . '.' . $key, $value, config('sentinel.cache.expire_after'));
        }
    }

    public static function getCache(string $key): mixed
    {
        if (in_array($key, self::CACHES)) {
            return self::getCacheDriver()->get(config('sentinel.cache.prefix') . '.' . $key);
        }

        return null;
    }

    public static function flushCache(): void
    {
        foreach (self::CACHES as $key) {
            self::getCacheDriver()->forget(config('sentinel.cache.prefix') . '.' . $key);
        }
    }

    private static function getCacheDriver()
    {
        $driver = config('sentinel.cache.driver');
        if ('default' === $driver) {
            $driver = config('cache.default');
        }

        return Cache::driver($driver);
    }
}
