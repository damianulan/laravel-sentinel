<?php

declare(strict_types=1);

namespace Sentinel\Tests\Fixtures;

use Sentinel\Contracts\PermissionRegistryContract;
use Sentinel\Contracts\RoleRegistryContract;

final class CoreRoleRegistry implements RoleRegistryContract
{
    public static function roles(): array
    {
        return [
            'root' => false,
            'admin' => true,
        ];
    }

    public static function rolePermissions(): array
    {
        return [
            'root' => ['*'],
            'admin' => ['users.view'],
        ];
    }

    public static function adminRoles(): array
    {
        return ['admin'];
    }
}

enum ProjectRoleRegistry: string implements RoleRegistryContract
{
    case ProjectManager = 'project-manager';

    public static function roles(): array
    {
        return [self::ProjectManager->value => true];
    }

    public static function rolePermissions(): array
    {
        return [
            'admin' => ['projects.view'],
            self::ProjectManager->value => ['projects.view'],
        ];
    }

    public static function adminRoles(): array
    {
        return [];
    }
}

enum CorePermissionRegistry: string implements PermissionRegistryContract
{
    case UsersView = 'users.view';

    public static function permissions(): array
    {
        return [self::UsersView->value => true];
    }
}

final class ProjectPermissionRegistry implements PermissionRegistryContract
{
    public static function permissions(): array
    {
        return ['projects.view' => false];
    }
}

final class ConflictingRoleRegistry implements RoleRegistryContract
{
    public static function roles(): array
    {
        return ['admin' => false];
    }

    public static function rolePermissions(): array
    {
        return [];
    }

    public static function adminRoles(): array
    {
        return [];
    }
}

abstract class AbstractRoleRegistry implements RoleRegistryContract
{
    public static function roles(): array
    {
        return [];
    }

    public static function rolePermissions(): array
    {
        return [];
    }

    public static function adminRoles(): array
    {
        return [];
    }
}
