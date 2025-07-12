<?php

namespace App\Traits;

use Sentinel\Contexts\System;
use Sentinel\Models\Permission;
use Sentinel\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as DBBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @author Damian Ułan <damian.ulan@protonmail.com>
 * @copyright 2025 damianulan
 */
trait HasRolesAndPermissions
{
    /**
     * Find all users with given role slugs.
     *
     * @param  mixed  ...$roles
     */
    public static function role(...$roles): Builder
    {
        return self::whereHas('roles', function (Builder $q) use ($roles) {
            $q->whereIn('slug', $roles);
        });
    }

    /**
     * Find all users with given permission slugs.
     *
     * @param  mixed  ...$permissions
     */
    public static function permission(...$permissions): Builder
    {
        return self::whereHas('permissions', function (Builder $q) use ($permissions) {
            $q->whereIn('slug', $permissions);
        });
    }

    /**
     * Context is not required, if not provided, checks for all contexts. System context is superior - if context is provided,
     * but assigned to System context, then it will return true.
     *
     * @param  mixed  $context  - \Illuminate\Database\Eloquent\Model instance
     * @return Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles($context = null): BelongsToMany
    {
        $relation = $this->morphToMany(Role::class, 'model', 'has_roles');
        if ($context && $context instanceof Model) {
            $system_context = new System;
            $relation = $this->morphToMany(Role::class, 'model', 'has_roles')->where(function (Builder $q) use ($context, $system_context) {
                $q->where(['context_type' => $context::class, 'context_id' => $context->id])
                    ->orWhere(['context_type' => $system_context::class]);
            });
        }

        return $relation;
    }

    /**
     * has_roles raw db representation
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function roleAssignments(): DBBuilder
    {
        $builder = DB::table('has_roles')->where('model_id', $this->id, 'model_type', $this->getMorphClass());

        return $builder;
    }

    /**
     * @return mixed
     */
    public function permissions()
    {
        return $this->morphToMany(Permission::class, 'model', 'has_permissions');
    }

    /**
     * Returns a Colletion of slugs with user roles being assigned to him.
     */
    public function getRoles(): Collection
    {
        return $this->roles->pluck('slug');
    }

    /**
     * Returns a Colletion of user's roles names based on langs.
     */
    public function getRolesNames(): Collection
    {
        $slugs = $this->roles->pluck('slug')->unique();
        $roles = new Collection;
        foreach ($slugs as $slug) {
            $roles->push(__('gates.roles.' . $slug));
        }

        return $roles;
    }

    /**
     * Check if has all of given roles.
     *
     * @param  string  $slug  - role slug
     * @return bool
     */
    public function hasRole(string $slug)
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
    public function hasRoles(array $roles)
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
    public function hasAnyRoles(array $roles)
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function hasPermission(Permission $permission)
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
    public function hasPermissionTo(Permission|string $permission, $context = null)
    {
        $perm = $permission;
        if ($permission instanceof Permission) {
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
     * @param  mixed  $context
     * @return bool
     */
    public function hasPermissionThroughRole(Permission $permission, $context = null)
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
     * @return mixed
     */
    public function getAllPermissions(array $permissions)
    {
        return Permission::where('slug', $permissions)->get();
    }

    /**
     * @param  mixed  ...$permissions
     * @return $this
     */
    public function givePermissionsTo(...$permissions)
    {
        $permissions = $this->getAllPermissions($permissions);
        if ($permissions === null) {
            return $this;
        }
        $this->permissions()->saveMany($permissions);

        return $this;
    }

    /**
     * @param  mixed  ...$permissions
     * @return $this
     */
    public function deletePermissions(...$permissions)
    {
        $permissions = $this->getAllPermissions($permissions);
        $this->permissions()->detach($permissions);

        return $this;
    }

    /**
     * @param  mixed  ...$permissions
     * @return HasRolesAndPermissions
     */
    public function refreshPermissions(...$permissions)
    {
        $this->permissions()->detach();

        return $this->givePermissionsTo($permissions);
    }

    /**
     * Assign role by its slug.
     *
     * @param  mixed  $slug
     * @param  mixed  $context
     * @return void
     */
    public function assignRoleSlug($slug, $context = null)
    {
        if (! $slug instanceof Role) {
            $role = Role::findBySlug($slug);
        }
        if ($role) {
            return $this->assignRoleType($role, $context);
        }

        return true;
    }

    /**
     * Assign role by its id.
     *
     * @param  mixed  $role_id
     * @param  mixed  $context
     * @return void
     */
    public function assignRole($role_id, $context = null)
    {
        if (! $role_id instanceof Role) {
            $role = Role::find($role_id);
        } else {
            $role = $role_id;
        }

        if ($role) {
            return $this->assignRoleType($role, $context);
        }

        return true;
    }

    /**
     * Revoke role by its slug.
     *
     * @param  mixed  $slug
     * @param  mixed  $context
     * @return void
     */
    public function revokeRoleSlug($slug, $context = null)
    {
        if (! $slug instanceof Role) {
            $role = Role::findBySlug($slug);
        } else {
            $role = $slug;
        }

        if ($role) {
            $this->revokeRoleType($role, $context);
        }

        return true;
    }

    /**
     * Revoke role by its id.
     *
     * @param  mixed  $role_id
     * @param  mixed  $context
     * @return void
     */
    public function revokeRole($role_id, $context = null)
    {
        if (! $role_id instanceof Role) {
            $role = Role::find($role_id);
        } else {
            $role = $role_id;
        }

        if ($role) {
            $this->revokeRoleType($role, $context);
        }

        return true;
    }

    private function assignRoleType(Role $role, $context = null)
    {
        $additional = [];

        if (! $context || ! ($context instanceof Model)) {
            $context = new System;
        }
        $additional['context_type'] = $context::class;
        $additional['context_id'] = $context->id;

        $this->roles()->attach($role, $additional);
    }

    /**
     * Revoking role by type context.
     *
     * @param  mixed  $context
     * @return void
     */
    private function revokeRoleType(Role $role, $context = null)
    {
        $additional = [];

        if (! $context || ! ($context instanceof Model)) {
            $context = new System;
        }
        $additional['context_type'] = $context::class;
        $additional['context_id'] = $context->id;

        $this->roles()->detach($role, $additional);
    }

    /**
     * Refresh role assignments.
     *
     * @param  mixed  $roles_ids
     * @return void
     */
    public function refreshRole($roles_ids = null)
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

        return true;
    }

    private function getMultiplePermissions(string $permission): array
    {
        $permissions = [];
        $m = [];
        $str = Str::of($permission);
        if ($str->contains('-*')) {
            $needle = $str->beforeLast('-*');
            $all = array_keys(PermissionLib::normal());
            $matches = array_filter($all, function ($value) use ($needle) {
                return Str::of($value)->contains($needle);
            });
            if (! empty($matches)) {
                $m = Permission::whereIn('slug', $matches)->get();
            }
        } else {
            $m = Permission::whereIn('slug', [$permission])->get();
        }

        if (! empty($m)) {
            $permissions = $m->all();
        }

        return $permissions;
    }

    /**
     * Check if has any of admin roles.
     *
     * @return bool
     */
    public function isAdmin()
    {
        return $this->hasAnyRoles(SystemRolesLib::admins());
    }

    public function isRoot(bool $strict = false)
    {
        if ($strict) {
            return $this->hasAnyRoles(['root']);
        }

        return $this->hasAnyRoles(['root', 'support']);
    }
}
