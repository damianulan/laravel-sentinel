<?php

declare(strict_types=1);

namespace Sentinel\Helpers;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Sentinel\Contracts\RoleContract;
use Sentinel\Exceptions\RoleNotFound;

final class RoleHelper
{
    /** @return Model&RoleContract */
    public static function findOrFail(mixed $role): Model
    {
        if ($role instanceof RoleContract && $role instanceof Model) {
            return $role;
        }

        if ($role instanceof BackedEnum) {
            $role = $role->value;
        }

        $roleClass = SentinelHelper::getRoleClass();
        $resolvedRole = is_int($role)
            ? $roleClass::query()->find($role)
            : self::findStringRole($roleClass, $role);

        if (null === $resolvedRole) {
            throw new RoleNotFound;
        }

        return $resolvedRole;
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
