<?php

namespace Sentinel\Config\Warden;

class RoleWarden extends Warden
{
    /**
     * Declare which roles are assignable throughout platform.
     */
    public static function assignable(): array
    {
        return [];
    }

    /**
     * Declare which roles are considered as admin roles.
     * When calling `isAdmin()` method, these roles will be checked if user has any of them assigned.
     */
    public static function admins(): array
    {
        return [];
    }
}
