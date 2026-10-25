<?php

declare(strict_types=1);

namespace Sentinel\Helpers;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Sentinel\Contracts\PermissionContract;
use Sentinel\Exceptions\PermissionNotFound;

final class PermissionHelper
{
    /** @return Model&PermissionContract */
    public static function findOrFail(mixed $permission): Model
    {
        if ($permission instanceof PermissionContract && $permission instanceof Model) {
            return $permission;
        }

        if ($permission instanceof BackedEnum) {
            $permission = $permission->value;
        }

        $permissionClass = SentinelHelper::getPermissionClass();
        $resolvedPermission = is_int($permission)
            ? $permissionClass::query()->find($permission)
            : self::findStringPermission($permissionClass, $permission);

        if (null === $resolvedPermission) {
            throw new PermissionNotFound;
        }

        return $resolvedPermission;
    }

    /**
     * @param  class-string<Model&PermissionContract>  $permissionClass
     * @return (Model&PermissionContract)|null
     */
    private static function findStringPermission(string $permissionClass, mixed $permission): ?Model
    {
        if ( ! is_string($permission) || '' === $permission) {
            return null;
        }

        return $permissionClass::findBySlug($permission) ?? $permissionClass::query()->find($permission);
    }
}
