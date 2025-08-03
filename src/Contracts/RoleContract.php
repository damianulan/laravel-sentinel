<?php

namespace Sentinel\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface RoleContract
{
    /**
     * Returns permissions that role is assigned to.
     */
    public function permissions(): BelongsToMany;

    /**
     * Finds role instance by its slug istead of its key.
     */
    public static function findBySlug(string $slug): ?static;

    /**
     * Get Role Key by its slug.
     */
    public static function getId(string $slug): ?string;

    /**
     * Get a list of assignable roles by their slugs.
     */
    public static function getSelectList(): array;

    /**
     * Get roles by their slugs.
     *
     * @param  mixed  ...$slugs
     */
    public function scopeWhereSlug(Builder $query, ...$slugs): void;

    /**
     * Get only assignable roles.
     */
    public function scopeWhereAssignable(Builder $query): void;
}
