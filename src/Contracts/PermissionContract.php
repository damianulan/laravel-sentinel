<?php

declare(strict_types=1);

namespace Sentinel\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** @mixin Model */
interface PermissionContract
{
    public static function findBySlug(string $slug): ?static;

    /** @return array<int|string, string> */
    public static function getSelectList(): array;

    /** @return BelongsToMany<Model&RoleContract, covariant Model> */
    public function roles(): BelongsToMany;

    public function getKey(): mixed;

    public function getAttribute(string $key): mixed;
}
