<?php

namespace Sentinel\Traits;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder as DBBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sentinel\Sentinel;
use Sentinel\Contracts\DefaultContext;
use Sentinel\Contracts\PermissionContract;
use Sentinel\Contracts\RoleContract;
use Sentinel\Helpers\PermissionHelper;
use Sentinel\Models\Permission;
use Sentinel\Models\Role;
use Sentinel\Helpers\RoleHelper;
use Sentinel\Helpers\SentinelHelper;

/**
 * @author Damian Ułan <damian.ulan@protonmail.com>
 * @copyright 2025 damianulan
 */
trait HasRolesAndPermissions
{
    /**
     * Context is not required, if not provided, checks for all contexts. System context is superior - if context is provided,
     * but assigned to System context, then it will return true.
     *
     * @param  Model|null  $context
     * @return MorphToMany
     */
    public function roles(?Model $context = null): MorphToMany
    {
        $role_class = config('sentinel.models.role');
        $relation = $this->morphToMany($role_class, 'model', 'has_roles');
        if ($context && $context instanceof Model) {
            $system_context = $this->getDefaultContext();
            $relation = $this->morphToMany($role_class, 'model', 'has_roles')->where(function (Builder $q) use ($context, $system_context): void {
                $q->where(['context_type' => $context::class, 'context_id' => $context->getKey()])
                    ->orWhere(['context_type' => $system_context::class]);
            });
        }

        return $relation;
    }

    /**
     * has_roles raw db representation
     */
    public function roleAssignments(): DBBuilder
    {
        return DB::table('has_roles')->where([
            'model_id' => $this->id,
            'model_type' => $this->getMorphClass(),
        ]);
    }

    public function permissions(): MorphToMany
    {
        return $this->morphToMany(config('sentinel.models.permission'), 'model', 'has_permissions');
    }

    /**
     * Returns a Colletion of slugs with user roles being assigned to him.
     */
    public function getRoles(): EloquentCollection
    {
        return $this->roles->pluck('slug');
    }

    /**
     * Returns a Colletion of user's roles names based on langs.
     */
    public function getRolesNames(): Collection
    {
        $slugs = $this->roles->pluck('slug')->unique();
        $roles = new Collection();
        $role_class = SentinelHelper::getRoleClass();
        $langs = $role_class::getRolesLib()::labels();
        foreach ($slugs as $slug) {
            $lang = $langs[$slug] ?? $slug;
            $roles->push($lang);
        }

        return $roles;
    }

    /**
     * Check if has given role.
     *
     * @param  RoleContract|string  $slug  - role slug
     */
    public function hasRole(RoleContract|string $slug): bool
    {
        $role = RoleHelper::findOrFail($slug);
        return (bool) ($this->roles->contains('slug', $role->slug));
    }

    /**
     * Check if has all of given roles.
     *
     * @param  array<mixed, RoleContract|string>  $roles  - role slugs
     */
    public function hasAllRoles(array $roles): bool
    {
        return $this->hasRoles($roles);
    }

    /**
     * Check if has all of given roles.
     *
     * @param  array<mixed, RoleContract|string>  $roles  - role slugs
     */
    public function hasRoles(array $roles): bool
    {
        foreach ($roles as $role) {
            if ( ! $this->hasRole($role)) {
                return false
            }
        }

        return true;
    }

    /**
     * Check if has any of given roles.
     *
     * @param  array<mixed, RoleContract|string>  $roles  - role slugs
     */
    public function hasAnyRoles(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if has given permission.
     * Please note, it does not verify the context - to check the context and verify with role, use hasPermissionTo instead.
     *
     * @param  PermissionContract|string  $permission
     * @return bool
     */
    public function hasPermission(PermissionContract|string $permission)
    {
        $permission = PermissionHelper::findOrFail($permission);
        return (bool) $this->permissions->where('slug', $permission->slug)->count();
    }

    /**
     * Check if user has a certain permission (direct or through role). Give model context if needed.
     * Use "permission-*" syntax to check for multiple permissions of given category.
     *
     * @param  PermissionContract|string  $permission
     * @param  Model|null  $context
     * @return bool
     */
    public function hasPermissionTo(PermissionContract|string $permission, ?Model $context = null)
    {
        if ($this->isRoot()) {
            return true;
        }

        $permissionSlug = PermissionHelper::findOrFail($permission)->slug;

        return Cache::store(SentinelHelper::getSentinelCacheStore())->remember(
            $this->getPermissionCheckCacheKey($permissionSlug, $context),
            now()->addSeconds(SentinelHelper::getSentinelCacheTtl()),
            function () use ($permissionSlug, $context): bool {
                $permissions = $this->getMultiplePermissions($permissionSlug);

                foreach ($permissions as $resolvedPermission) {
                    $result = $context
                        ? $this->hasPermissionThroughRole($resolvedPermission, $context)
                        : ($this->hasPermissionThroughRole($resolvedPermission) || $this->hasPermission($resolvedPermission));

                    if ($result) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    /**
     * Check if user-assigned role has a permission.
     *
     * @param  PermissionContract|string  $permission
     * @param  Model|null  $context
     */
    public function hasPermissionThroughRole(PermissionContract|string $permission, ?Model $context = null): bool
    {
        $roles = $this->roles($context)->get();
        foreach ($permission->roles as $role) {
            if ($roles->contains($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all permissions by their slugs.
     *
     * @param  array  $permissions  - array containing permission slugs
     */
    public function getAllPermissions(array $permissions): EloquentCollection
    {
        $perm_class = config('sentinel.models.permission');

        return $perm_class::where('slug', $permissions)->get();
    }

    /**
     * Assign direct permissions.
     *
     * @param  mixed  ...$permissions  slugs
     */
    public function givePermissionsTo(...$permissions): static
    {
        $permissions = $this->getAllPermissions($permissions);
        if (null === $permissions) {
            return $this;
        }
        $this->permissions()->saveMany($permissions);
        $this->flushSentinelPermissionCache();

        return $this;
    }

    /**
     * Unassign direct permissions.
     *
     * @param  mixed  ...$permissions  slugs
     */
    public function deletePermissions(...$permissions): static
    {
        $permissions = $this->getAllPermissions($permissions);
        $this->permissions()->detach($permissions);
        $this->flushSentinelPermissionCache();

        return $this;
    }

    /**
     * Refresh permission assignments with their slugs.
     *
     * @param  mixed  ...$permissions  slugs
     */
    public function refreshPermissions(...$permissions): static
    {
        $this->permissions()->detach();

        $this->flushSentinelPermissionCache();

        return $this->givePermissionsTo($permissions);
    }

    /**
     * Assign role by its slug.
     *
     * @param  RoleContract|string   $slug
     * @param  mixed                 $context
     * @deprecated 1.0.7
     */
    public function assignRoleSlug(RoleContract|string $slug, $context = null): static
    {
        return $this->assignRole($slug, $context);
    }

    /**
     * Assign role by its slug.
     *
     * @param  RoleContract|string   $slug
     * @param  Model|null            $context
     */
    public function assignRole(RoleContract|string $slug, ?Model $context = null): static
    {
        $this->assignRoleType(RoleHelper::findOrFail($slug), $context);

        return $this;
    }

    /**
     * Assign role by its id.
     *
     * @param  RoleContract|int  $role_id
     * @param  Model|null        $context
     */
    public function assignRoleId(RoleContract|int $role_id, ?Model $context = null): static
    {
        $this->assignRoleType(RoleHelper::findOrFail($role_id), $context);

        return $this;
    }

    /**
     * Revoke role by its slug.
     *
     * @param  RoleContract|string   $slug
     * @param  Model|null            $context
     * @deprecated 1.0.7
     */
    public function revokeRoleSlug(RoleContract|string $slug, ?Model $context = null): static
    {
        return $this->revokeRole($slug, $context);
    }

    /**
     * Revoke role by its slug.
     *
     * @param  RoleContract|string   $slug
     * @param  Model|null            $context
     */
    public function revokeRole(RoleContract|string $slug, ?Model $context = null): static
    {
        $this->revokeRoleType(RoleHelper::findOrFail($slug), $context);

        return $this;
    }


    /**
     * Revoke role by its id.
     *
     * @param  RoleContract|int  $role_id
     * @param  Model|null        $context
     */
    public function revokeRoleId($role_id, ?Model $context = null): static
    {
        $this->revokeRoleType(RoleHelper::findOrFail($role_id), $context);

        return $this;
    }

    /**
     * Refresh role assignments.
     *
     * @param  array  $roles_ids
     */
    public function refreshRole(array $roles_ids = []): static
    {
        $current = $this->roles()->where('assignable', 1)->get()->pluck('id')->toArray();

        $toDelete = array_filter($current, fn ($value) => ! in_array($value, $roles_ids));
        $toAdd = array_filter($roles_ids, fn ($value) => ! in_array($value, $current));

        foreach ($toDelete as $role_id) {
            $this->revokeRoleId($role_id);
        }
        foreach ($toAdd as $role_id) {
            $this->assignRoleId($role_id);
        }

        $this->flushSentinelPermissionCache();

        return $this;
    }

    /**
     * Check if has any of admin roles, determined by RoleWarden.
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRoles(Role::getRolesLib()::admins()) || $this->isRoot();
    }

    /**
     * Checks if is a superuser.
     */
    public function isRoot(): bool
    {
        return $this->hasRole(config(('sentinel.root')));
    }

    /**
     * Loads records with given roles by their slugs.
     *
     * @param  mixed  ...$slugs
     */
    protected function scopeWithRole(Builder $query, ...$slugs): void
    {
        $query->whereHas('roles', function (Builder $q) use ($slugs): void {
            $q->whereIn('slug', $slugs);
        });
    }

    /**
     * Loads records with given permissions by their slugs.
     *
     * @param  mixed  ...$slugs
     */
    protected function scopeWithPermission(Builder $query, ...$slugs): void
    {
        $query->where(function (Builder $q) use ($slugs): void {
            $q->where(function (Builder $q) use ($slugs): void {
                $q->whereHas('permissions', function (Builder $q) use ($slugs): void {
                    $q->whereIn('slug', $slugs);
                });
            })->orWhere(function (Builder $q) use ($slugs): void {
                $q->whereHas('roles', function (Builder $q) use ($slugs): void {
                    $q->whereHas('permissions', function (Builder $q) use ($slugs): void {
                        $q->whereIn('slug', $slugs);
                    });
                });
            });
        });
    }

    private function getDefaultContext(): DefaultContext
    {
        $sys = config('sentinel.default_context');
        $context = new $sys();
        if ( ! $context instanceof DefaultContext) {
            throw new Exception('Default context must implement ' . DefaultContext::class);
        }

        return $context;
    }

    /**
     * Assign role by type context.
     *
     * @param  mixed  $context
     */
    private function assignRoleType(RoleContract $role, $context = null): void
    {
        $additional = [];

        if ( ! $context || ! ($context instanceof Model)) {
            $context = $this->getDefaultContext();
        }
        $additional['context_type'] = $context::class;
        $additional['context_id'] = $context->getKey();

        if ( ! $this->roles()->where('context_type', $additional['context_type'])->where('context_id', $additional['context_id'])->where('role_id', $role->id)->exists()) {
            $this->roles()->attach($role, $additional);
            $this->flushSentinelPermissionCache();
        }
    }

    /**
     * Revoking role by type context.
     *
     * @param  mixed  $context
     */
    private function revokeRoleType(RoleContract $role, ?Model $context = null): void
    {
        $additional = [];

        if ( ! $context || ! ($context instanceof Model)) {
            $context = $this->getDefaultContext();
        }
        $additional['context_type'] = $context::class;
        $additional['context_id'] = $context->getKey();

        $this->roles()->detach($role, $additional);
        $this->flushSentinelPermissionCache();
    }

    private function getMultiplePermissions(string $permission): array
    {
        $permissions = [];
        $m = [];
        $str = Str::of($permission);
        $perm_class = config('sentinel.models.permission');
        if ($str->contains('-*')) {
            $needle = $str->beforeLast('-*');
            $all = array_keys(Permission::getPermissionsLib()::assignable());
            $matches = array_filter($all, fn ($value) => Str::of($value)->contains($needle));
            if ( ! empty($matches)) {
                $m = $perm_class::whereIn('slug', $matches)->get();
            }
        } else {
            $m = $perm_class::whereIn('slug', [$permission])->get();
        }

        if ( ! empty($m)) {
            $permissions = $m->all();
        }

        return $permissions;
    }

    private function flushSentinelPermissionCache(): void
    {
        Cache::store(SentinelHelper::getSentinelCacheStore())->forever(
            $this->getPermissionCacheVersionKey(),
            Str::uuid()->toString()
        );
    }

    private function getPermissionCheckCacheKey(string $permission, $context = null): string
    {
        $parts = [
            config('sentinel.cache.key', 'sentinel.cache'),
            'permission-check',
            $this->getMorphClass(),
            $this->getKey(),
            $this->getPermissionCacheVersion(),
            $this->getGlobalPermissionCacheVersion(),
            $permission,
        ];

        if ($context instanceof Model) {
            $parts[] = $context::class;
            $parts[] = (string) $context->getKey();
        } else {
            $parts[] = 'global';
        }

        return implode('.', array_map(
            static fn (string|int $part): string => Str::slug((string) $part, '_'),
            $parts
        ));
    }

    private function getPermissionCacheVersion(): string
    {
        $store = Cache::store(SentinelHelper::getSentinelCacheStore());
        $key = $this->getPermissionCacheVersionKey();

        return (string) $store->rememberForever($key, static fn (): string => Str::uuid()->toString());
    }

    private function getGlobalPermissionCacheVersion(): string
    {
        $store = Cache::store(SentinelHelper::getSentinelCacheStore());
        $key = config('sentinel.cache.key', 'sentinel.cache') . '.permission-checks.version';

        return (string) $store->rememberForever($key, static fn (): string => Str::uuid()->toString());
    }

    private function getPermissionCacheVersionKey(): string
    {
        return implode('.', [
            config('sentinel.cache.key', 'sentinel.cache'),
            'permission-checks',
            Str::slug($this->getMorphClass(), '_'),
            $this->getKey(),
            'version',
        ]);
    }
}
