<?php

namespace Sentinel\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface PermissionContract
{
    /**
     * Returns roles that have this permission assigned.
     */
    public function roles(): BelongsToMany;

    /**
     * Finds permission instance by its slug istead of its key.
     */
    public static function findBySlug(string $slug): ?static;

    /**
     * Get a list of assignable permissions by their slugs.
     */
    public static function getSelectList(): array;

    /**
     * Get permissions by their slugs.
     *
     * @param  mixed  ...$slugs
     */
    public function scopeWhereSlug(Builder $query, ...$slugs): void;

    /**
     * Get only assignable permissions.
     */
    public function scopeWhereAssignable(Builder $query): void;
}
