<?php

namespace Sentinel\Config\Warden;

use Sentinel\Config\Warden;

abstract readonly class PermissionWarden extends Warden
{
    abstract public static function assignable(): array;

    abstract public static function nonassignable(): array;
}
