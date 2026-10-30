<?php

declare(strict_types=1);

namespace Sentinel\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final readonly class PermissionCache
{
    public function __construct(
        private Repository $store,
        private string $prefix,
        private int $ttl,
        private bool $enabled,
    ) {}

    /** @param Closure(): bool $resolver */
    public function remember(
        Model $authorizable,
        string $permission,
        ?Model $context,
        Closure $resolver,
    ): bool {
        if ( ! $this->canCache($authorizable, $context)) {
            return $resolver();
        }

        return (bool) $this->store->remember(
            $this->resultKey($authorizable, $permission, $context),
            $this->ttl,
            $resolver,
        );
    }

    public function invalidate(Model $authorizable): void
    {
        if ( ! $this->enabled || $this->ttl <= 0 || null === $authorizable->getKey()) {
            return;
        }

        $this->store->put(
            $this->subjectVersionKey($authorizable),
            $this->newVersion(),
            $this->versionTtl(),
        );
    }

    public function invalidateAll(): void
    {
        if ( ! $this->enabled || $this->ttl <= 0) {
            return;
        }

        $this->store->forever($this->globalVersionKey(), $this->newVersion());
    }

    private function canCache(Model $authorizable, ?Model $context): bool
    {
        if ( ! $this->enabled || $this->ttl <= 0 || null === $authorizable->getKey()) {
            return false;
        }

        return null === $context || null !== $context->getKey();
    }

    private function resultKey(Model $authorizable, string $permission, ?Model $context): string
    {
        $identity = [
            $authorizable->getMorphClass(),
            $this->modelKey($authorizable),
            $this->subjectVersion($authorizable),
            $this->globalVersion(),
            $permission,
            $context?->getMorphClass() ?? 'global',
            null === $context ? '' : $this->modelKey($context),
        ];

        return $this->key('result', hash('sha256', implode("\0", $identity)));
    }

    private function subjectVersion(Model $authorizable): string
    {
        $version = $this->store->remember(
            $this->subjectVersionKey($authorizable),
            $this->versionTtl(),
            fn (): string => $this->newVersion(),
        );

        return $version;
    }

    private function globalVersion(): string
    {
        $version = $this->store->rememberForever(
            $this->globalVersionKey(),
            fn (): string => $this->newVersion(),
        );

        return $version;
    }

    private function subjectVersionKey(Model $authorizable): string
    {
        $identity = $authorizable->getMorphClass() . "\0" . $this->modelKey($authorizable);

        return $this->key('subject', hash('sha256', $identity));
    }

    private function globalVersionKey(): string
    {
        return $this->key('global');
    }

    private function modelKey(Model $model): string
    {
        $key = $model->getKey();

        return is_int($key) || is_string($key) ? (string) $key : '';
    }

    private function key(string ...$segments): string
    {
        return implode(':', [$this->normalizedPrefix(), 'permissions', ...$segments]);
    }

    private function normalizedPrefix(): string
    {
        $prefix = preg_replace('/[^A-Za-z0-9_.:-]/', '_', $this->prefix);

        return is_string($prefix) && '' !== $prefix ? $prefix : 'sentinel';
    }

    private function versionTtl(): int
    {
        return max($this->ttl * 2, $this->ttl + 60);
    }

    private function newVersion(): string
    {
        return Str::uuid()->toString();
    }
}
