<?php

declare(strict_types=1);

namespace Sentinel\Traits;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use RuntimeException;
use Sentinel\Cache\PermissionCache;
use Sentinel\Contracts\PermissionContract;
use Sentinel\Contracts\RoleContract;
use Sentinel\Helpers\PermissionHelper;
use Sentinel\Helpers\RoleHelper;
use Sentinel\Helpers\SentinelHelper;
use Sentinel\Registry\RegistryRepository;

/**
 * @mixin Model
 *
 * @author Damian Ułan <damian.ulan@protonmail.com>
 * @copyright 2026 damianulan
 */
trait HasRolesAndPermissions
{
    /**
     * Return roles from every context, or roles from the given context plus
     * roles assigned to the default system context.
     */
    /** @return MorphToMany<Model&RoleContract, $this, MorphPivot, 'pivot'> */
    public function roles(?Model $context = null): MorphToMany
    {
        $relation = $this->morphToMany(
            SentinelHelper::getRoleClass(),
            'model',
            'has_roles',
        );

        if (null === $context) {
            return $relation;
        }

        [$contextType, $contextId] = $this->contextIdentity($context);
        [$defaultType, $defaultId] = $this->contextIdentity();

        $relation->where(function (Builder $query) use ($contextType, $contextId, $defaultType, $defaultId): void {
            $query
                ->where(function (Builder $query) use ($contextType, $contextId): void {
                    $query->where('has_roles.context_type', $contextType)
                        ->where('has_roles.context_id', $contextId);
                })
                ->orWhere(function (Builder $query) use ($defaultType, $defaultId): void {
                    $query->where('has_roles.context_type', $defaultType)
                        ->where('has_roles.context_id', $defaultId);
                });
        });

        return $relation;
    }

    public function roleAssignments(): QueryBuilder
    {
        return DB::table('has_roles')->where([
            'model_id' => $this->getKey(),
            'model_type' => $this->getMorphClass(),
        ]);
    }

    /** @return MorphToMany<Model&PermissionContract, $this, MorphPivot, 'pivot'> */
    public function permissions(): MorphToMany
    {
        return $this->morphToMany(
            SentinelHelper::getPermissionClass(),
            'model',
            'has_permissions',
        );
    }

    /** @return Collection<int, string> */
    public function getRoles(): Collection
    {
        /** @var Collection<int, string> $roles */
        $roles = collect();

        foreach ($this->roles()->pluck('slug') as $slug) {
            if (is_string($slug)) {
                $roles->push($slug);
            }
        }

        return $roles->unique()->values();
    }

    /** @return Collection<int, string> */
    public function getRolesNames(): Collection
    {
        return $this->getRoles()->map(function (string $slug): string {
            $translationKey = "gates.roles.{$slug}";

            $translation = __($translationKey);

            return Lang::has($translationKey) && is_string($translation)
                ? $translation
                : $slug;
        });
    }

    public function hasRole(RoleContract|BackedEnum|string|int $role): bool
    {
        $role = RoleHelper::findOrFail($role);

        return $this->roles()->whereKey($role->getKey())->exists();
    }

    /** @param array<int, RoleContract|BackedEnum|string|int> $roles */
    public function hasAllRoles(array $roles): bool
    {
        return $this->hasRoles($roles);
    }

    /** @param array<int, RoleContract|BackedEnum|string|int> $roles */
    public function hasRoles(array $roles): bool
    {
        $roleIds = $this->roleIds($roles);

        if ([] === $roleIds) {
            return true;
        }

        return $this->roles()
            ->whereKey($roleIds)
            ->distinct()
            ->count() === count($roleIds);
    }

    /** @param array<int, RoleContract|BackedEnum|string|int> $roles */
    public function hasAnyRoles(array $roles): bool
    {
        $roleIds = $this->roleIds($roles);

        return [] !== $roleIds && $this->roles()->whereKey($roleIds)->exists();
    }

    public function hasPermission(PermissionContract|BackedEnum|string|int $permission): bool
    {
        $permission = PermissionHelper::findOrFail($permission);

        return $this->permissions()->whereKey($permission->getKey())->exists();
    }

    public function hasPermissionTo(
        PermissionContract|BackedEnum|string|int $permission,
        ?Model $context = null,
    ): bool {
        $cacheIdentity = $this->permissionIdentity($permission);

        return $this->permissionCache()->remember(
            $this,
            $cacheIdentity,
            $context,
            function () use ($permission, $context): bool {
                if ($this->isRoot()) {
                    return true;
                }

                $permissionSlugs = $this->resolvePermissionSlugs($permission);

                if ([] === $permissionSlugs) {
                    return false;
                }

                if ($this->hasAnyPermissionThroughRole($permissionSlugs, $context)) {
                    return true;
                }

                return null === $context
                    && $this->permissions()->whereIn('slug', $permissionSlugs)->exists();
            },
        );
    }

    public function hasPermissionThroughRole(
        PermissionContract|BackedEnum|string|int $permission,
        ?Model $context = null,
    ): bool {
        $permission = PermissionHelper::findOrFail($permission);

        return $this->hasAnyPermissionThroughRole(
            [$this->permissionSlug($permission)],
            $context,
        );
    }

    /**
     * @param  array<array-key, mixed>  $permissions
     * @return EloquentCollection<int, Model&PermissionContract>
     */
    public function getAllPermissions(array $permissions): EloquentCollection
    {
        $resolved = collect(Arr::flatten($permissions))
            ->map(fn (mixed $permission): PermissionContract => PermissionHelper::findOrFail($permission))
            ->unique(fn (PermissionContract $permission): mixed => $permission->getKey())
            ->values()
            ->all();

        return SentinelHelper::newPermissionModel()->newCollection($resolved);
    }

    public function givePermissionsTo(mixed ...$permissions): static
    {
        $changes = $this->permissions()->syncWithoutDetaching(
            $this->getAllPermissions($permissions)->modelKeys(),
        );

        if ([] !== $changes['attached'] || [] !== $changes['updated']) {
            $this->invalidatePermissionCache();
        }

        return $this;
    }

    public function deletePermissions(mixed ...$permissions): static
    {
        $detached = $this->permissions()->detach(
            $this->getAllPermissions($permissions)->modelKeys(),
        );

        if ($detached > 0) {
            $this->invalidatePermissionCache();
        }

        return $this;
    }

    public function refreshPermissions(mixed ...$permissions): static
    {
        $changes = $this->permissions()->sync(
            $this->getAllPermissions($permissions)->modelKeys(),
        );

        if ([] !== $changes['attached'] || [] !== $changes['detached'] || [] !== $changes['updated']) {
            $this->invalidatePermissionCache();
        }

        return $this;
    }

    /** @deprecated 1.0.7 Use assignRole(). */
    public function assignRoleSlug(
        RoleContract|BackedEnum|string $role,
        ?Model $context = null,
    ): static {
        return $this->assignRole($role, $context);
    }

    public function assignRole(
        RoleContract|BackedEnum|string $role,
        ?Model $context = null,
    ): static {
        $this->assignRoleModel(RoleHelper::findOrFail($role), $context);

        return $this;
    }

    public function assignRoleId(
        RoleContract|int $role,
        ?Model $context = null,
    ): static {
        $this->assignRoleModel(RoleHelper::findOrFail($role), $context);

        return $this;
    }

    /** @deprecated 1.0.7 Use revokeRole(). */
    public function revokeRoleSlug(
        RoleContract|BackedEnum|string $role,
        ?Model $context = null,
    ): static {
        return $this->revokeRole($role, $context);
    }

    public function revokeRole(
        RoleContract|BackedEnum|string $role,
        ?Model $context = null,
    ): static {
        $this->revokeRoleModel(RoleHelper::findOrFail($role), $context);

        return $this;
    }

    public function revokeRoleId(
        RoleContract|int $role,
        ?Model $context = null,
    ): static {
        $this->revokeRoleModel(RoleHelper::findOrFail($role), $context);

        return $this;
    }

    /** @param array<int, RoleContract|BackedEnum|string|int> $roles */
    public function refreshRole(array $roles = []): static
    {
        [$contextType, $contextId] = $this->contextIdentity();
        $targetRoleIds = $this->roleIds($roles);
        $roleClass = SentinelHelper::getRoleClass();
        $assignableRoleIds = $roleClass::query()
            ->whereKey($targetRoleIds)
            ->where('assignable', true)
            ->pluck((new $roleClass)->getKeyName())
            ->all();
        $assignableRoleIds = array_values(array_filter(
            $assignableRoleIds,
            static fn (mixed $roleId): bool => is_int($roleId) || is_string($roleId),
        ));

        $currentRoleIds = $this->roles()
            ->wherePivot('context_type', $contextType)
            ->wherePivot('context_id', $contextId)
            ->where('assignable', true)
            ->pluck((new $roleClass)->getQualifiedKeyName())
            ->all();
        $currentRoleIds = array_values(array_filter(
            $currentRoleIds,
            static fn (mixed $roleId): bool => is_int($roleId) || is_string($roleId),
        ));

        sort($assignableRoleIds);
        sort($currentRoleIds);

        foreach (array_diff($currentRoleIds, $assignableRoleIds) as $roleId) {
            $this->revokeRoleModel(RoleHelper::findOrFail($roleId), invalidate: false);
        }

        foreach (array_diff($assignableRoleIds, $currentRoleIds) as $roleId) {
            $this->assignRoleModel(RoleHelper::findOrFail($roleId), invalidate: false);
        }

        if ($currentRoleIds !== $assignableRoleIds) {
            $this->invalidatePermissionCache();
        }

        return $this;
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRoles(app(RegistryRepository::class)->adminRoles())
            || $this->isRoot();
    }

    public function isRoot(): bool
    {
        return $this->roles()
            ->where('slug', SentinelHelper::getRootRole())
            ->exists();
    }

    /** @param Builder<static> $query */
    public function scopeWithRole(Builder $query, mixed ...$roles): void
    {
        $roleIds = $this->roleIds(Arr::flatten($roles));

        $query->whereHas('roles', fn (Builder $query) => $query->whereKey($roleIds));
    }

    /** @param Builder<static> $query */
    public function scopeWithPermission(Builder $query, mixed ...$permissions): void
    {
        $permissionIds = $this->getAllPermissions(Arr::flatten($permissions))->modelKeys();

        $query->where(function (Builder $query) use ($permissionIds): void {
            $query
                ->whereHas('permissions', fn (Builder $query) => $query->whereKey($permissionIds))
                ->orWhereHas(
                    'roles.permissions',
                    fn (Builder $query) => $query->whereKey($permissionIds),
                );
        });
    }

    /**
     * @param  array<int, RoleContract|BackedEnum|string|int>  $roles
     * @return array<int, mixed>
     */
    private function roleIds(array $roles): array
    {
        return collect(Arr::flatten($roles))
            ->map(fn (mixed $role): RoleContract => RoleHelper::findOrFail($role))
            ->map(fn (RoleContract $role): mixed => $role->getKey())
            ->unique()
            ->values()
            ->all();
    }

    /** @return array{0: string, 1: int|string} */
    private function contextIdentity(?Model $context = null): array
    {
        if (null !== $context) {
            $key = $context->getKey();

            if ( ! is_int($key) && ! is_string($key)) {
                throw new RuntimeException('Sentinel contexts must be persisted before roles are assigned or checked.');
            }

            return [$context->getMorphClass(), $key];
        }

        $defaultContext = SentinelHelper::getDefaultContext();

        return [$defaultContext::class, $defaultContext->getKey()];
    }

    private function assignRoleModel(
        RoleContract $role,
        ?Model $context = null,
        bool $invalidate = true,
    ): void {
        [$contextType, $contextId] = $this->contextIdentity($context);

        $exists = $this->roleAssignments()
            ->where('role_id', $role->getKey())
            ->where('context_type', $contextType)
            ->where('context_id', $contextId)
            ->exists();

        if ($exists) {
            return;
        }

        $this->roles()->attach($role->getKey(), [
            'context_type' => $contextType,
            'context_id' => $contextId,
        ]);

        if ($invalidate) {
            $this->invalidatePermissionCache();
        }
    }

    private function revokeRoleModel(
        RoleContract $role,
        ?Model $context = null,
        bool $invalidate = true,
    ): void {
        [$contextType, $contextId] = $this->contextIdentity($context);

        $deleted = $this->roleAssignments()
            ->where('role_id', $role->getKey())
            ->where('context_type', $contextType)
            ->where('context_id', $contextId)
            ->delete();

        if ($invalidate && $deleted > 0) {
            $this->invalidatePermissionCache();
        }
    }

    /** @param array<int, string> $permissionSlugs */
    private function hasAnyPermissionThroughRole(array $permissionSlugs, ?Model $context): bool
    {
        return $this->roles($context)
            ->whereHas('permissions', function (Builder $query) use ($permissionSlugs): void {
                $query->whereIn('slug', $permissionSlugs);
            })
            ->exists();
    }

    /** @return array<int, string> */
    private function resolvePermissionSlugs(
        PermissionContract|BackedEnum|string|int $permission,
    ): array {
        $identity = $this->permissionIdentity($permission);

        if ( ! str_ends_with($identity, '*')) {
            $permission = PermissionHelper::findOrFail($permission);

            return [$this->permissionSlug($permission)];
        }

        $prefix = mb_substr($identity, 0, -1);

        return array_values(array_filter(
            array_keys(app(RegistryRepository::class)->permissions()),
            static fn (string $slug): bool => str_starts_with($slug, $prefix),
        ));
    }

    private function permissionIdentity(
        PermissionContract|BackedEnum|string|int $permission,
    ): string {
        if ($permission instanceof PermissionContract) {
            return $this->permissionSlug($permission);
        }

        if ($permission instanceof BackedEnum) {
            return (string) $permission->value;
        }

        return (string) $permission;
    }

    private function permissionSlug(PermissionContract $permission): string
    {
        $slug = $permission->getAttribute('slug');

        if ( ! is_string($slug) || '' === $slug) {
            throw new RuntimeException('Sentinel permission models must contain a non-empty string slug.');
        }

        return $slug;
    }

    private function permissionCache(): PermissionCache
    {
        return app(PermissionCache::class);
    }

    private function invalidatePermissionCache(): void
    {
        $this->unsetRelation('roles');
        $this->unsetRelation('permissions');
        $this->permissionCache()->invalidate($this);
    }
}
