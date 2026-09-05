<?php

namespace Sentinel\Helpers;

class SentinelHelper
{
    public static function getRoleClass(): string
    {
        return config('sentinel.models.role');
    }

    public static function getPermissionClass(): string
    {
        return config('sentinel.models.permission');
    }

    public static function getSentinelCacheStore(): string
    {
        $driver = config('sentinel.cache.driver', 'default');

        return 'default' === $driver
            ? (string) config('cache.default')
            : $driver;
    }

    public static function getSentinelCacheTtl(): int
    {
        return (int) config('sentinel.cache.expire_after', 86400);
    }
}
