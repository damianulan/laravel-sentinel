<?php

namespace Sentinel\Helpers;

use BackedEnum;
use Sentinel\Contracts\PermissionContract;
use Sentinel\Exceptions\PermissionNotFound;
use Sentinel\Helpers\SentinelHelper;

class PermissionHelper
{
    public static function findOrFail(
        mixed $permission_id
    ): PermissionContract
    {
        if($permission_id instanceof BackedEnum){
            $permission_id = $permission_id->value;
        }

        $perm_class = SentinelHelper::getPermissionClass();
        if ( ! $permission_id instanceof PermissionContract) {
            if(is_int($permission_id)){
                $permission_id = $perm_class::find($permission_id);
            } else {
                $permission_id = $perm_class::findBySlug($permission_id) ?? $perm_class::find($permission_id);
            }
        }

        if(empty($permission_id)){
            throw new PermissionNotFound;
        }

        return $permission_id;
    }
}
