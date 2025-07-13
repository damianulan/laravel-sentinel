<?php

namespace Sentinel\Config;

use Illuminate\Support\Facades\Cache;
use Sentinel\Exceptions\RoleWardenException;
use Sentinel\Exceptions\PermissionWardenException;
use Sentinel\Config\Warden\PermissionWarden;
use Sentinel\Config\Warden\RoleWarden;
use ReflectionClass;

class SentinelManager
{
    public static function getRolesLibNamespace(): ?string
    {
        $value = Cache::get('sentinel.rolesLib');

        if (empty($value)) {
            $parentClass = RoleWarden::class;
            // Get all declared classes from Composer's autoloader
            $classMap = require base_path('vendor/composer/autoload_classmap.php');
            $classMap = array_filter($classMap, function ($key) {
                return strpos($key, 'App\\Warden') !== false;
            }, ARRAY_FILTER_USE_KEY);

            foreach (array_keys($classMap) as $class) {
                if (!class_exists($class)) {
                    continue;
                }

                try {
                    $reflection = new ReflectionClass($class);

                    if (
                        $reflection->isSubclassOf($parentClass) &&
                        !$reflection->isAbstract()
                    ) {
                        $value = $class;
                    }
                } catch (ReflectionException $e) {
                    // Skip problematic classes
                }
            }
            Cache::put('sentinel.rolesLib', $value);
        }

        return $value;
    }

    public static function getPermissionsLibNamespace(): ?string
    {
        $value = Cache::get('sentinel.permissionsLib');

        if (empty($value)) {
            $parentClass = PermissionWarden::class;
            // Get all declared classes from Composer's autoloader
            $classMap = require base_path('vendor/composer/autoload_classmap.php');
            $classMap = array_filter($classMap, function ($key) {
                return strpos($key, 'App\\Warden') !== false;
            }, ARRAY_FILTER_USE_KEY);

            foreach (array_keys($classMap) as $class) {
                if (!class_exists($class)) {
                    continue;
                }

                try {
                    $reflection = new ReflectionClass($class);

                    if (
                        $reflection->isSubclassOf($parentClass) &&
                        !$reflection->isAbstract()
                    ) {
                        $value = $class;
                    }
                } catch (ReflectionException $e) {
                    // Skip problematic classes
                }
            }
            Cache::put('sentinel.permissionsLib', $value);
        }

        return $value;
    }

    public static function getRolesLib(): RoleWarden
    {
        $value = self::getRolesLibNamespace();
        if (empty($value)) {
            throw new RoleWardenException;
        }

        return new $value();
    }

    public static function getPermissionsLib(): PermissionWarden
    {
        $value = self::getPermissionsLibNamespace();
        if (empty($value)) {
            throw new PermissionWardenException;
        }

        return new $value();
    }
}
