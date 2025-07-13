<?php

namespace Sentinel\Config\Warden;

use Sentinel\Config\Warden\Warden;

class PermissionWarden extends Warden
{
    public static function assignable(): array
    {
        return [];
    }

    public static function nonassignable(): array
    {
        return [];
    }
}
