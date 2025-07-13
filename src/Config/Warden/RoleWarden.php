<?php

namespace Sentinel\Config\Warden;

use Sentinel\Config\Warden\Warden;

class RoleWarden extends Warden
{
    public static function assignable(): array
    {
        return [];
    }

    public static function admins(): array
    {
        return [];
    }
}
