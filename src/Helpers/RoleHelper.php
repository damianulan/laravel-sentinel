<?php

namespace Sentinel\Helpers;

use BackedEnum;
use Sentinel\Contracts\RoleContract;
use Sentinel\Exceptions\RoleNotFound;
use Sentinel\Helpers\SentinelHelper;

class RoleHelper
{
    public static function findOrFail(
        mixed $role_id
    ): RoleContract
    {
        if($role_id instanceof BackedEnum){
            $role_id = $role_id->value;
        }

        $role_class = SentinelHelper::getRoleClass();
        if ( ! $role_id instanceof RoleContract) {
            if(is_int($role_id)){
                $role_id = $role_class::find($role_id);
            } else {
                $role_id = $role_class::findBySlug($role_id) ?? $role_class::find($role_id);
            }
        }

        if(empty($role_id)){
            throw new RoleNotFound;
        }

        return $role_id;
    }
}
