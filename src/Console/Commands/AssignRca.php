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
    private $bar;

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
        $this->info('Loading Sentinel roles and permissions...');

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

        $roles = $this->rolesLib::values();
        $permissions = $this->permissionsLib::values();
        $progressCount = count($roles) + count($permissions);
        $this->bar = $this->output->createProgressBar($progressCount);
        $this->bar->start();

        $this->setRoles();
        $this->setPermissions();

        $this->bar->finish();
        $this->info('Sentinel roles and permissions loaded successfully!');

        $toDeletePermissions = Permission::whereNotIn('slug', $permissions)->get();
        $toDeleteRoles = Role::whereNotIn('slug', $roles)->get();
        $deletionRecords = $toDeletePermissions->count() + $toDeleteRoles->count();

        if ($deletionRecords > 0) {
            $this->info('Deleting outdated ' . $toDeletePermissions->count() . ' permissions and ' . $toDeleteRoles->count() . ' roles');
            $bar = $this->output->createProgressBar($deletionRecords);
            $bar->start();
            foreach ($toDeletePermissions as $permission) {
                $permission->delete();
                $bar->advance();
            }
            foreach ($toDeleteRoles as $role) {
                $role->delete();
                $bar->advance();
            }
            $bar->finish();
            $this->info('Outdated Sentinel roles and permissions deleted successfully!');
        }
    }

    public function setRoles(): void
    {
        foreach ($this->rolesLib::values() as $name) {
            $this->bar->advance();
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
            $this->bar->advance();
            if (Permission::whereSlug($slug)->exists()) {
                continue;
            }
            $perm = new Permission;
            $perm->slug = $slug;
            $perm->assignable = false;
            if ($perm->save()) {
                $this->attach($perm, $roles);
            }
        }

        foreach ($this->permissionsLib::assignable() as $slug => $roles) {
            $this->bar->advance();
            if (Permission::whereSlug($slug)->exists()) {
                continue;
            }
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
