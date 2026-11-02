<?php

declare(strict_types=1);

namespace Sentinel\Helpers;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Sentinel\Contracts\RoleContract;

final class RoleHelper
{
    /** @return (Model&RoleContract)|null */
    public static function find(mixed $role): ?Model
    {
        if ($role instanceof RoleContract && $role instanceof Model) {
            return $role;
        }

        if ($role instanceof BackedEnum) {
            $role = $role->value;
        }

        $roleClass = SentinelHelper::getRoleClass();

        return is_int($role)
            ? $roleClass::query()->find($role)
            : self::findStringRole($roleClass, $role);
    }

    /**
     * @param  class-string<Model&RoleContract>  $roleClass
     * @return (Model&RoleContract)|null
     */
    private static function findStringRole(string $roleClass, mixed $role): ?Model
    {
        if ( ! is_string($role) || '' === $role) {
            return null;
        }

        return $roleClass::findBySlug($role) ?? $roleClass::query()->find($role);
    }
}
