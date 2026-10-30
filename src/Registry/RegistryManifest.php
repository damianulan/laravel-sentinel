<?php

declare(strict_types=1);

namespace Sentinel\Registry;

final readonly class RegistryManifest
{
    /**
     * @param  array<string, bool>  $roles
     * @param  array<string, bool>  $permissions
     * @param  array<string, array<int, string>>  $rolePermissions
     * @param  array<int, string>  $adminRoles
     * @param  array<int, class-string>  $roleRegistries
     * @param  array<int, class-string>  $permissionRegistries
     */
    public function __construct(
        public array $roles,
        public array $permissions,
        public array $rolePermissions,
        public array $adminRoles,
        public array $roleRegistries,
        public array $permissionRegistries,
    ) {}

    public function registryCount(): int
    {
        return count($this->roleRegistries) + count($this->permissionRegistries);
    }
}
