<?php

namespace Sentinel\Traits;

use Barryvdh\LaravelIdeHelper\Eloquent;
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
            $relation = $this->morphToMany($role_class, 'model', 'has_roles')->where(function (Builder $q) use ($context, $system_context) {
                $q->where(['context_type' => $context::class, 'context_id' => $context->getKey()])
                    ->orWhere(['context_type' => $system_context::class]);
            });
        }

        return $relation;
    }

    private function getDefaultContext(): DefaultContext
    {
        $sys = config('sentinel.default_context');
        $context = new $sys;
        if (! $context instanceof DefaultContext) {
            throw new \Exception('Default context must implement ' . DefaultContext::class);
        }

        return $context;
    }

    /**
     * has_roles raw db representation
     */
    public function roleAssignments(): DBBuilder
    {
        $builder = DB::table('has_roles')->where('model_id', $this->id, 'model_type', $this->getMorphClass());

        return $builder;
    }

    public function permissions(): MorphToMany
    {
        return $this->morphToMany(config('sentinel.models.permission'), 'model', 'has_permissions');
    }

    /**
     * Returns a Colletion of slugs with user roles being assigned to him.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRoles(): EloquentCollection
    {
        return $this->roles->pluck('slug');
    }

    /**
     * Returns a Colletion of user's roles names based on langs.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getRolesNames(): Collection
    {
        $slugs = $this->roles->pluck('slug')->unique();
        $roles = new Collection;
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
     * @return bool
     */
    public function hasRole(string $slug): bool
    {
        if ($this->roles->contains('slug', $slug)) {
            return true;
        }

        return false;
    }

    /**
     * Check if has all of given roles.
     *
     * @param  array  $roles  - role slugs
     * @return bool
     */
    public function hasRoles(array $roles): bool
    {
        $result = true;
        foreach ($roles as $role) {
            if (! $this->hasRole($role)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Check if has any of given roles.
     *
     * @param  array  $roles  - role slugs
     * @return bool
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
     *
     * @param \Sentinel\Contracts\PermissionContract $permission
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

    /**
     * Check if user has a certain permission (direct or through role). Give model context if needed.
     * Use "permission-*" syntax to check for multiple permissions of given category.
     *
     * @param \Sentinel\Contracts\PermissionContract|string $permission
     * @param mixed                                         $context
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
            $result = $this->hasPermissionThroughRole($p, $context) || $this->hasPermission($p);
            if ($result) {
                break;
            }
        }

        return $result;
    }

    /**
     * @param \Sentinel\Contracts\PermissionContract $permission
     * @param mixed                                  $context
     * @return bool
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
     * @param array $permissions - array containing permission slugs
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllPermissions(array $permissions): EloquentCollection
    {
        $perm_class = config('sentinel.models.permission');

        return $perm_class::where('slug', $permissions)->get();
    }

    /**
     * Assign direct permissions.
     *
     * @param mixed ...$permissions slugs
     * @return static
     */
    public function givePermissionsTo(...$permissions): static
    {
        $permissions = $this->getAllPermissions($permissions);
        if ($permissions === null) {
            return $this;
        }
        $this->permissions()->saveMany($permissions);

        return $this;
    }

    /**
     * Unassign direct permissions.
     *
     * @param mixed ...$permissions slugs
     * @return static
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
     * @param mixed ...$permissions slugs
     * @return static
     */
    public function refreshPermissions(...$permissions): static
    {
        $this->permissions()->detach();

        return $this->givePermissionsTo($permissions);
    }

    /**
     * Assign role by its slug.
     *
     * @param \Sentinel\Contracts\RoleContract|string $slug
     * @param mixed                                   $context
     * @return void
     */
    public function assignRoleSlug(RoleContract|string $slug, $context = null): void
    {
        $role_class = config('sentinel.models.role');
        if (! $slug instanceof RoleContract) {
            $role = $role_class::findBySlug($slug);
        }
        if ($role) {
            $this->assignRoleType($role, $context);
        }
    }

    /**
     * Assign role by its id.
     *
     * @param  mixed  $role_id
     * @param  mixed  $context
     * @return void
     */
    public function assignRole($role_id, $context = null): void
    {
        $role_class = config('sentinel.models.role');
        if (! $role_id instanceof RoleContract) {
            $role = $role_class::find($role_id);
        } else {
            $role = $role_id;
        }

        if ($role) {
            $this->assignRoleType($role, $context);
        }
    }

    /**
     * Revoke role by its slug.
     *
     * @param \Sentinel\Contracts\RoleContract|string $slug
     * @param mixed                                   $context
     * @return void
     */
    public function revokeRoleSlug(RoleContract|string $slug, $context = null): void
    {
        $role_class = config('sentinel.models.role');
        if (! $slug instanceof RoleContract) {
            $role = $role_class::findBySlug($slug);
        } else {
            $role = $slug;
        }

        if ($role) {
            $this->revokeRoleType($role, $context);
        }
    }

    /**
     * Revoke role by its id.
     *
     * @param  mixed  $role_id
     * @param  mixed  $context
     * @return void
     */
    public function revokeRole($role_id, $context = null): void
    {
        $role_class = config('sentinel.models.role');
        if (! $role_id instanceof RoleContract) {
            $role = $role_class::find($role_id);
        } else {
            $role = $role_id;
        }

        if ($role) {
            $this->revokeRoleType($role, $context);
        }
    }

    /**
     * Assign role by type context.
     *
     * @param \Sentinel\Contracts\RoleContract $role
     * @param mixed                            $context
     * @return void
     */
    private function assignRoleType(RoleContract $role, $context = null): void
    {
        $additional = [];

        if (! $context || ! ($context instanceof Model)) {
            $context = $this->getDefaultContext();
        }
        $additional['context_type'] = $context::class;
        $additional['context_id'] = $context->getKey();

        $this->roles()->attach($role, $additional);
    }

    /**
     * Revoking role by type context.
     *
     * @param \Sentinel\Contracts\RoleContract $role
     * @param mixed                            $context
     * @return void
     */
    private function revokeRoleType(RoleContract $role, $context = null): void
    {
        $additional = [];

        if (! $context || ! ($context instanceof Model)) {
            $context = $this->getDefaultContext();
        }
        $additional['context_type'] = $context::class;
        $additional['context_id'] = $context->getKey();

        $this->roles()->detach($role, $additional);
    }

    /**
     * Refresh role assignments.
     *
     * @param  mixed  $roles_ids
     * @return void
     */
    public function refreshRole($roles_ids = null): void
    {
        if (! $roles_ids) {
            $roles_ids = [];
        }

        $current = $this->roles()->where('assignable', 1)->get()->pluck('id')->toArray();

        $toDelete = array_filter($current, function ($value) use ($roles_ids) {
            return ! in_array($value, $roles_ids);
        });
        $toAdd = array_filter($roles_ids, function ($value) use ($current) {
            return ! in_array($value, $current);
        });

        foreach ($toDelete as $role_id) {
            $this->revokeRole($role_id);
        }
        foreach ($toAdd as $role_id) {
            $this->assignRole($role_id);
        }
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
            $matches = array_filter($all, function ($value) use ($needle) {
                return Str::of($value)->contains($needle);
            });
            if (! empty($matches)) {
                $m = $perm_class::whereIn('slug', $matches)->get();
            }
        } else {
            $m = $perm_class::whereIn('slug', [$permission])->get();
        }

        if (! empty($m)) {
            $permissions = $m->all();
        }

        return $permissions;
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
        $query->whereHas('roles', function (Builder $q) use ($slugs) {
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
        $query->where(function (Builder $q) use ($slugs) {
            $q->where(function (Builder $q) use ($slugs) {
                $q->whereHas('permissions', function (Builder $q) use ($slugs) {
                    $q->where('slug', $slugs);
                });
            })->orWhere(function (Builder $q) use ($slugs) {
                $q->whereHas('roles', function (Builder $q) use ($slugs) {
                    $q->whereHas('permissions', function (Builder $q) use ($slugs) {
                        $q->where('slug', $slugs);
                    });
                });
            });
        });
    }
}
