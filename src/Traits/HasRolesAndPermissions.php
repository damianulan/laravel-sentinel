<?php

namespace Sentinel\Traits;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder as DBBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Sentinel\Contracts\DefaultContext;
use Sentinel\Contracts\PermissionContract;
use Sentinel\Contracts\RoleContract;
use Sentinel\Models\Permission;
use Sentinel\Models\Role;

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
     * @param  mixed  $context  - \Illuminate\Database\Eloquent\Model instance
     * @return Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function roles($context = null): MorphToMany
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
        $langs = Role::getRolesLib()::labels();
        foreach ($slugs as $slug) {
            $lang = $langs[$slug] ?? $slug;
            $roles->push($lang);
        }

        return $roles;
    }

    /**
     * Check if has given role.
     *
     * @param  string  $slug  - role slug
     */
    public function hasRole(string $slug): bool
    {
        return (bool) ($this->roles->contains('slug', $slug));
    }

    /**
     * Check if has all of given roles.
     *
     * @param  array  $roles  - role slugs
     */
    public function hasRoles(array $roles): bool
    {
        $result = true;
        foreach ($roles as $role) {
            if ( ! $this->hasRole($role)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Check if has any of given roles.
     *
     * @param  array  $roles  - role slugs
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
     * @return bool
     */
    public function hasPermission(PermissionContract $permission)
    {
        return (bool) $this->permissions->where('slug', $permission->slug)->count();
    }

    /**
     * Check if user has a certain permission (direct or through role). Give model context if needed.
     * Use "permission-*" syntax to check for multiple permissions of given category.
     *
     * @param  mixed  $context
     * @return bool
     */
    public function hasPermissionTo(PermissionContract|string $permission, $context = null)
    {
        $perm = $permission;
        if ($permission instanceof PermissionContract) {
            $perm = $permission->slug;
        }

        $permissions = $this->getMultiplePermissions($perm);
        $result = false;

        foreach ($permissions as $p) {
            $result = $context ? $this->hasPermissionThroughRole($p, $context) : $this->hasPermissionThroughRole($p) || $this->hasPermission($p);
            if ($result) {
                break;
            }
        }

        return $result;
    }

    /**
     * @param  mixed  $context
     */
    public function hasPermissionThroughRole(PermissionContract $permission, $context = null): bool
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

        return $this->givePermissionsTo($permissions);
    }

    /**
     * Assign role by its slug.
     *
     * @param  mixed  $context
     */
    public function assignRoleSlug(RoleContract|string $slug, $context = null): static
    {
        $role_class = config('sentinel.models.role');
        if ( ! $slug instanceof RoleContract) {
            $role = $role_class::findBySlug($slug);
        }
        if ($role) {
            $this->assignRoleType($role, $context);
        }

        return $this;
    }

    /**
     * Assign role by its id.
     *
     * @param  mixed  $role_id
     * @param  mixed  $context
     */
    public function assignRole($role_id, $context = null): static
    {
        $role_class = config('sentinel.models.role');
        if ( ! $role_id instanceof RoleContract) {
            $role = $role_class::find($role_id);
        } else {
            $role = $role_id;
        }

        if ($role) {
            $this->assignRoleType($role, $context);
        }

        return $this;
    }

    /**
     * Revoke role by its slug.
     *
     * @param  mixed  $context
     */
    public function revokeRoleSlug(RoleContract|string $slug, $context = null): static
    {
        $role_class = config('sentinel.models.role');
        if ( ! $slug instanceof RoleContract) {
            $role = $role_class::findBySlug($slug);
        } else {
            $role = $slug;
        }

        if ($role) {
            $this->revokeRoleType($role, $context);
        }

        return $this;
    }

    /**
     * Revoke role by its id.
     *
     * @param  mixed  $role_id
     * @param  mixed  $context
     */
    public function revokeRole($role_id, $context = null): static
    {
        $role_class = config('sentinel.models.role');
        if ( ! $role_id instanceof RoleContract) {
            $role = $role_class::find($role_id);
        } else {
            $role = $role_id;
        }

        if ($role) {
            $this->revokeRoleType($role, $context);
        }

        return $this;
    }

    /**
     * Refresh role assignments.
     *
     * @param  mixed  $roles_ids
     */
    public function refreshRole($roles_ids = null): static
    {
        if ( ! $roles_ids) {
            $roles_ids = [];
        }

        $current = $this->roles()->where('assignable', 1)->get()->pluck('id')->toArray();

        $toDelete = array_filter($current, fn ($value) => ! in_array($value, $roles_ids));
        $toAdd = array_filter($roles_ids, fn ($value) => ! in_array($value, $current));

        foreach ($toDelete as $role_id) {
            $this->revokeRole($role_id);
        }
        foreach ($toAdd as $role_id) {
            $this->assignRole($role_id);
        }

        return $this;
    }

    /**
     * Check if has any of admin roles, determined by RoleWarden.
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRoles(Role::getRolesLib()::admins());
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
        }
    }

    /**
     * Revoking role by type context.
     *
     * @param  mixed  $context
     */
    private function revokeRoleType(RoleContract $role, $context = null): void
    {
        $additional = [];

        if ( ! $context || ! ($context instanceof Model)) {
            $context = $this->getDefaultContext();
        }
        $additional['context_type'] = $context::class;
        $additional['context_id'] = $context->getKey();

        $this->roles()->detach($role, $additional);
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
}
