<?php

namespace Sentinel\Config\Warden;

class PermissionWarden extends Warden
{
    /**
     * Declare which permissions are assignable throughout platform.
     */
    public static function assignable(): array
    {
        return [];
    }

    /**
     * Declare which permissions are non-assignable throughout platform.
     */
    public static function nonassignable(): array
    {
        return [];
    }
}
