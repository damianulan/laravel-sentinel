<?php

declare(strict_types=1);

namespace Sentinel\Contracts;

interface PermissionRegistryContract
{
    /**
     * Return permission slugs keyed by their assignable state.
     *
     * @return array<string, bool>
     */
    public static function permissions(): array;
}
