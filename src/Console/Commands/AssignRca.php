<?php

namespace Sentinel\Console\Commands;

use Illuminate\Console\Command;
use Sentinel\Models\Role;
use Sentinel\Models\Permission;
use Sentinel\Config\SentinelManager;

class AssignRca extends Command
{
    private $rolesLib;
    private $permissionsLib;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sentinel:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run Roles and Permissions assignment';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $rolesLib = SentinelManager::getRolesLibNamespace();
        $permissionsLib = SentinelManager::getPermissionsLibNamespace();

        if (empty($rolesLib)) {
            $this->error('Role Warden class is not declared in the project!');
            return false;
        } else {
            $this->rolesLib = new $rolesLib();
        }
        if (empty($permissionsLib)) {
            $this->error('Permission Warden class is not declared in the project!');
            return false;
        } else {
            $this->permissionsLib = new $permissionsLib();
        }

        $this->setRoles();
        $this->setPermissions();
    }

    public function setRoles(): void
    {
        foreach ($this->rolesLib::values() as $name) {
            if (Role::whereSlug($name)->exists()) {
                continue;
            }
            $this->$name = new Role;
            $this->$name->slug = $name;
            $this->$name->assignable = in_array($name, $this->rolesLib::assignable());
            $this->$name->save();
        }
    }

    private function setPermissions()
    {
        foreach ($this->permissionsLib::nonassignable() as $slug => $roles) {
            $perm = new Permission;
            $perm->slug = $slug;
            $perm->assignable = false;
            if ($perm->save()) {
                $this->attach($perm, $roles);
            }
        }

        foreach ($this->permissionsLib::assignable() as $slug => $roles) {
            $perm = new Permission;
            $perm->slug = $slug;
            $perm->assignable = true;
            if ($perm->save()) {
                $this->attach($perm, $roles);
            }
        }
    }

    private function attach(Permission $permission, array $to)
    {
        $roles = $this->rolesLib::values();

        foreach ($to as $slug) {
            if ($slug === 'admins') {
                foreach ($this->rolesLib::admins() as $role) {
                    $this->$role->permissions()->attach($permission);
                }
            } elseif ($slug === '*') {
                foreach ($roles as $role) {
                    $this->$role->permissions()->attach($permission);
                }
            } else {
                $this->$slug->permissions()->attach($permission);
            }
        }
    }
}
