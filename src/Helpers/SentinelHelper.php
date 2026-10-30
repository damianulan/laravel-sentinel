<?php

declare(strict_types=1);

namespace Sentinel\Helpers;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Sentinel\Contracts\DefaultContext;
use Sentinel\Contracts\PermissionContract;
use Sentinel\Contracts\RoleContract;
use Sentinel\Models\Permission;
use Sentinel\Models\Role;

final class SentinelHelper
{
    /** @return class-string<Model&RoleContract> */
    public static function getRoleClass(): string
    {
        $class = config('sentinel.models.role', Role::class);

        if ( ! is_string($class) || ! is_a($class, Model::class, true) || ! is_a($class, RoleContract::class, true)) {
            throw new InvalidArgumentException('The configured Sentinel role model must extend Eloquent Model and implement RoleContract.');
        }

        /** @var class-string<Model&RoleContract> $class */
        return $class;
    }

    /** @return class-string<Model&PermissionContract> */
    public static function getPermissionClass(): string
    {
        $class = config('sentinel.models.permission', Permission::class);

        if ( ! is_string($class) || ! is_a($class, Model::class, true) || ! is_a($class, PermissionContract::class, true)) {
            throw new InvalidArgumentException('The configured Sentinel permission model must extend Eloquent Model and implement PermissionContract.');
        }

        /** @var class-string<Model&PermissionContract> $class */
        return $class;
    }

    /** @return Model&RoleContract */
    public static function newRoleModel(): Model
    {
        $class = self::getRoleClass();

        return new $class;
    }

    /** @return Model&PermissionContract */
    public static function newPermissionModel(): Model
    {
        $class = self::getPermissionClass();

        return new $class;
    }

    public static function getDefaultContext(): DefaultContext
    {
        $class = config('sentinel.default_context');

        if ( ! is_string($class) || ! is_a($class, DefaultContext::class, true)) {
            throw new InvalidArgumentException('The configured Sentinel default context must implement DefaultContext.');
        }

        return new $class;
    }

    public static function getRootRole(): string
    {
        $role = config('sentinel.root', 'root');

        return is_string($role) && '' !== $role ? $role : 'root';
    }

    public static function getSentinelCacheStore(): ?string
    {
        $driver = config('sentinel.cache.driver', 'default');

        if ('default' === $driver) {
            $driver = config('cache.default');
        }

        return is_string($driver) && '' !== $driver ? $driver : null;
    }

    public static function getSentinelCacheKey(): string
    {
        $key = config('sentinel.cache.key', 'sentinel.cache');

        return is_string($key) && '' !== $key ? $key : 'sentinel.cache';
    }

    public static function getSentinelCacheTtl(): int
    {
        $ttl = config('sentinel.cache.expire_after', 86400);

        return max(0, is_numeric($ttl) ? (int) $ttl : 86400);
    }

    public static function isSentinelCacheEnabled(): bool
    {
        $enabled = config('sentinel.cache.enabled', true);

        return is_bool($enabled) ? $enabled : true;
    }
}
