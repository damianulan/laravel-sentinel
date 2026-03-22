<?php

namespace Sentinel\Concerns;

use Illuminate\Support\Facades\Cache;

trait HasSentinelCache
{
    public static function putCache(string $key, $value): void
    {
        if (in_array($key, static::CACHES)) {
            static::getCacheDriver()->put(config('sentinel.cache.key') . '.' . $key, $value, config('sentinel.cache.expire_after'));
        }
    }

    public static function getCache(string $key): mixed
    {
        if (in_array($key, static::CACHES)) {
            return static::getCacheDriver()->get(config('sentinel.cache.key') . '.' . $key);
        }

        return null;
    }

    public static function flushCache(): void
    {
        foreach (static::CACHES as $key) {
            static::getCacheDriver()->forget(config('sentinel.cache.key') . '.' . $key);
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
