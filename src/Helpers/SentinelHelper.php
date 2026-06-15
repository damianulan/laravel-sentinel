<?php

namespace Sentinel\Helpers;

class SentinelHelper
{
    public static function getRoleClass(): string
    {
        return config('sentinel.models.role');
    }

    public static function getPermissionClass(): string
    {
        return config('sentinel.models.permission');
    }
}
