<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Database\Eloquent\Model;
use Sentinel\Cache\PermissionCache;

function sentinelCacheModel(int $id, string $table = 'users'): Model
{
    $model = new class extends Model
    {
        public $timestamps = false;
    };

    $model->setTable($table);
    $model->setRawAttributes(['id' => $id], true);
    $model->exists = true;

    return $model;
}

function sentinelPermissionCache(bool $enabled = true, int $ttl = 300): PermissionCache
{
    return new PermissionCache(
        store: new Repository(new ArrayStore),
        prefix: 'sentinel.test',
        ttl: $ttl,
        enabled: $enabled,
    );
}

it('caches permission results for a subject and context', function (): void {
    $cache = sentinelPermissionCache();
    $user = sentinelCacheModel(1);
    $context = sentinelCacheModel(10, 'projects');
    $calls = 0;

    $first = $cache->remember($user, 'projects.view', $context, function () use (&$calls): bool {
        $calls++;

        return true;
    });
    $second = $cache->remember($user, 'projects.view', $context, function () use (&$calls): bool {
        $calls++;

        return false;
    });

    expect($first)->toBeTrue()
        ->and($second)->toBeTrue()
        ->and($calls)->toBe(1);
});

it('invalidates only the changed subject', function (): void {
    $cache = sentinelPermissionCache();
    $firstUser = sentinelCacheModel(1);
    $secondUser = sentinelCacheModel(2);
    $calls = ['first' => 0, 'second' => 0];

    $cache->remember($firstUser, 'users.view', null, function () use (&$calls): bool {
        $calls['first']++;

        return false;
    });
    $cache->remember($secondUser, 'users.view', null, function () use (&$calls): bool {
        $calls['second']++;

        return false;
    });

    $cache->invalidate($firstUser);

    $cache->remember($firstUser, 'users.view', null, function () use (&$calls): bool {
        $calls['first']++;

        return true;
    });
    $cache->remember($secondUser, 'users.view', null, function () use (&$calls): bool {
        $calls['second']++;

        return true;
    });

    expect($calls)->toBe(['first' => 2, 'second' => 1]);
});

it('invalidates every subject after global authorization changes', function (): void {
    $cache = sentinelPermissionCache();
    $user = sentinelCacheModel(1);
    $calls = 0;

    $cache->remember($user, 'users.view', null, function () use (&$calls): bool {
        $calls++;

        return false;
    });

    $cache->invalidateAll();

    $result = $cache->remember($user, 'users.view', null, function () use (&$calls): bool {
        $calls++;

        return true;
    });

    expect($result)->toBeTrue()
        ->and($calls)->toBe(2);
});

it('bypasses cache when disabled or the subject is not persisted', function (): void {
    $disabledCache = sentinelPermissionCache(enabled: false);
    $enabledCache = sentinelPermissionCache();
    $persistedUser = sentinelCacheModel(1);
    $newUser = new class extends Model {};
    $calls = 0;
    $resolver = function () use (&$calls): bool {
        $calls++;

        return true;
    };

    $disabledCache->remember($persistedUser, 'users.view', null, $resolver);
    $disabledCache->remember($persistedUser, 'users.view', null, $resolver);
    $enabledCache->remember($newUser, 'users.view', null, $resolver);
    $enabledCache->remember($newUser, 'users.view', null, $resolver);

    expect($calls)->toBe(4);
});
