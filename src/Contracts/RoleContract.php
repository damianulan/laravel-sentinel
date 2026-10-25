<?php

declare(strict_types=1);

namespace Sentinel\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** @mixin Model */
interface RoleContract
{
    public static function findBySlug(string $slug): ?static;

    public static function getId(string $slug): int|string|null;

    /** @return array<int|string, string> */
    public static function getSelectList(): array;

    /** @return BelongsToMany<Model&PermissionContract, covariant Model> */
    public function permissions(): BelongsToMany;

    public function getAttribute(string $key): mixed;
}
