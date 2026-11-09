<?php

declare(strict_types=1);

namespace Sentinel\Contracts;

interface RoleRegistryContract
{
    /**
     * Return role slugs keyed by their assignable state.
     *
     * @return array<string, bool>
     */
    public static function roles(): array;

    /**
     * Return permission slugs grouped by role slug.
     *
     * The `*` permission grants every registered permission to the role.
     *
     * @return array<string, array<int, string>>
     */
    public static function rolePermissions(): array;

    /** @return array<int, string> */
    public static function adminRoles(): array;
}
