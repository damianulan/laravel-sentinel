<?php

declare(strict_types=1);

namespace Sentinel\Registry;

use InvalidArgumentException;
use Sentinel\Contracts\PermissionRegistryContract;
use Sentinel\Contracts\RoleRegistryContract;

final class RegistryRepository
{
    private ?RegistryManifest $manifest = null;

    public function __construct(private readonly RegistryDiscovery $discovery) {}

    public function manifest(): RegistryManifest
    {
        return $this->manifest ??= $this->buildManifest();
    }

    /** @return array<int, class-string<RoleRegistryContract>> */
    public function roleRegistries(): array
    {
        /** @var array<int, class-string<RoleRegistryContract>> $registries */
        $registries = $this->manifest()->roleRegistries;

        return $registries;
    }

    /** @return array<int, class-string<PermissionRegistryContract>> */
    public function permissionRegistries(): array
    {
        /** @var array<int, class-string<PermissionRegistryContract>> $registries */
        $registries = $this->manifest()->permissionRegistries;

        return $registries;
    }

    /** @return array<string, bool> */
    public function roles(): array
    {
        return $this->manifest()->roles;
    }

    /** @return array<string, bool> */
    public function permissions(): array
    {
        return $this->manifest()->permissions;
    }

    /** @return array<string, array<int, string>> */
    public function rolePermissions(): array
    {
        return $this->manifest()->rolePermissions;
    }

    /** @return array<int, string> */
    public function adminRoles(): array
    {
        return $this->manifest()->adminRoles;
    }

    private function buildManifest(): RegistryManifest
    {
        $roleRegistries = $this->discoverRoleRegistries();
        $permissionRegistries = $this->discoverPermissionRegistries();

        if ([] === $roleRegistries) {
            throw new InvalidArgumentException('No role registries implementing RoleRegistryContract were found in Composer\'s classmap.');
        }

        if ([] === $permissionRegistries) {
            throw new InvalidArgumentException('No permission registries implementing PermissionRegistryContract were found in Composer\'s classmap.');
        }

        $roles = $this->mergeRoleDefinitions($roleRegistries);
        $permissions = $this->mergePermissionDefinitions($permissionRegistries);
        $rolePermissions = $this->mergeRolePermissions($roleRegistries);
        $adminRoles = $this->mergeAdminRoles($roleRegistries);

        $this->validateReferences($roles, $permissions, $rolePermissions, $adminRoles);

        return new RegistryManifest(
            roles: $roles,
            permissions: $permissions,
            rolePermissions: $rolePermissions,
            adminRoles: $adminRoles,
            roleRegistries: $roleRegistries,
            permissionRegistries: $permissionRegistries,
        );
    }

    /** @return array<int, class-string<RoleRegistryContract>> */
    private function discoverRoleRegistries(): array
    {
        /** @var array<int, class-string<RoleRegistryContract>> $registries */
        $registries = $this->discovery->implementing(RoleRegistryContract::class);

        return $registries;
    }

    /** @return array<int, class-string<PermissionRegistryContract>> */
    private function discoverPermissionRegistries(): array
    {
        /** @var array<int, class-string<PermissionRegistryContract>> $registries */
        $registries = $this->discovery->implementing(PermissionRegistryContract::class);

        return $registries;
    }

    /**
     * @param  array<int, class-string<RoleRegistryContract>>  $registries
     * @return array<string, bool>
     */
    private function mergeRoleDefinitions(array $registries): array
    {
        $roles = [];

        foreach ($registries as $registry) {
            $roles = $this->mergeDefinitions($roles, $registry::roles(), $registry);
        }

        return $roles;
    }

    /**
     * @param  array<int, class-string<PermissionRegistryContract>>  $registries
     * @return array<string, bool>
     */
    private function mergePermissionDefinitions(array $registries): array
    {
        $permissions = [];

        foreach ($registries as $registry) {
            $permissions = $this->mergeDefinitions($permissions, $registry::permissions(), $registry);
        }

        return $permissions;
    }

    /**
     * @param  array<int, class-string<RoleRegistryContract>>  $registries
     * @return array<string, array<int, string>>
     */
    private function mergeRolePermissions(array $registries): array
    {
        $rolePermissions = [];

        foreach ($registries as $registry) {
            foreach ($registry::rolePermissions() as $role => $permissions) {
                $this->assertSlug($role, $registry);

                foreach ($permissions as $permission) {
                    $this->assertSlug($permission, $registry);
                }

                $rolePermissions[$role] = array_values(array_unique([
                    ...($rolePermissions[$role] ?? []),
                    ...$permissions,
                ]));
            }
        }

        return $rolePermissions;
    }

    /**
     * @param  array<int, class-string<RoleRegistryContract>>  $registries
     * @return array<int, string>
     */
    private function mergeAdminRoles(array $registries): array
    {
        $roles = [];

        foreach ($registries as $registry) {
            foreach ($registry::adminRoles() as $role) {
                $this->assertSlug($role, $registry);
                $roles[] = $role;
            }
        }

        return array_values(array_unique($roles));
    }

    /**
     * @param  array<string, bool>  $definitions
     * @param  array<mixed, mixed>  $incoming
     * @param  class-string  $registry
     * @return array<string, bool>
     */
    private function mergeDefinitions(array $definitions, array $incoming, string $registry): array
    {
        foreach ($incoming as $slug => $assignable) {
            $this->assertSlug($slug, $registry);

            if ( ! is_bool($assignable)) {
                throw new InvalidArgumentException("Registry [{$registry}] must define [{$slug}] with a boolean assignable state.");
            }

            if (array_key_exists($slug, $definitions) && $definitions[$slug] !== $assignable) {
                throw new InvalidArgumentException("Registry [{$registry}] conflicts with another definition of [{$slug}].");
            }

            $definitions[$slug] = $assignable;
        }

        return $definitions;
    }

    /**
     * @param  array<string, bool>  $roles
     * @param  array<string, bool>  $permissions
     * @param  array<string, array<int, string>>  $rolePermissions
     * @param  array<int, string>  $adminRoles
     */
    private function validateReferences(
        array $roles,
        array $permissions,
        array $rolePermissions,
        array $adminRoles,
    ): void {
        foreach ($rolePermissions as $role => $assignedPermissions) {
            if ( ! array_key_exists($role, $roles)) {
                throw new InvalidArgumentException("Permissions are assigned to unknown role [{$role}].");
            }

            foreach ($assignedPermissions as $permission) {
                if ('*' !== $permission && ! array_key_exists($permission, $permissions)) {
                    throw new InvalidArgumentException("Role [{$role}] references unknown permission [{$permission}].");
                }
            }
        }

        foreach ($adminRoles as $role) {
            if ( ! array_key_exists($role, $roles)) {
                throw new InvalidArgumentException("Unknown role [{$role}] is registered as an administrator role.");
            }
        }
    }

    /** @param class-string $registry */
    private function assertSlug(mixed $slug, string $registry): void
    {
        if ( ! is_string($slug) || 1 !== preg_match('/^[^\s]+$/u', $slug)) {
            throw new InvalidArgumentException("Registry [{$registry}] contains an invalid slug.");
        }
    }
}
