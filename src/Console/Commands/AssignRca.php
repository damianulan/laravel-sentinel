<?php

namespace Sentinel\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Sentinel\Config\SentinelManager;
use Sentinel\Exceptions\PermissionWardenException;
use Sentinel\Exceptions\RoleWardenException;
use Sentinel\Models\Permission;
use Sentinel\Models\Role;

class AssignRca extends Command
{
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
    protected $description = 'Run Role and Permission assignments';

    private $rolesLib;

    private $permissionsLib;

    private $bar;

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        try {
            DB::beginTransaction();
            $rolesLib = SentinelManager::getRolesLibNamespace();
            $permissionsLib = SentinelManager::getPermissionsLibNamespace();
            $this->line('Loading Sentinel roles and permissions...');

            if (empty($rolesLib)) {
                throw new RoleWardenException();
            }
            $this->rolesLib = new $rolesLib();

            if (empty($permissionsLib)) {
                throw new PermissionWardenException();
            }
            $this->permissionsLib = new $permissionsLib();

            $roles = $this->rolesLib::values();
            $permissions = $this->permissionsLib::values();
            $progressCount = count($roles) + count($permissions);
            $this->bar = $this->output->createProgressBar($progressCount);
            $this->bar->start();

            $this->setRoles();
            $this->setPermissions();

            $this->bar->finish();
            $this->newLine();
            $this->info('Sentinel roles and permissions loaded successfully!');

            $toDeletePermissions = Permission::whereNotIn('slug', $permissions)->get();
            $toDeleteRoles = Role::whereNotIn('slug', $roles)->get();
            $deletionRecords = $toDeletePermissions->count() + $toDeleteRoles->count();

            if ($deletionRecords > 0) {
                $this->line('Deleting outdated ' . $toDeletePermissions->count() . ' permissions and ' . $toDeleteRoles->count() . ' roles');
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
                $this->newLine();
                $this->info('Outdated Sentinel roles and permissions deleted successfully!');
            }
            DB::commit();

            SentinelManager::flushCache();
        } catch (Exception $e) {
            DB::rollBack();
            $this->error($e->getMessage());
        }
    }

    public function setRoles(): void
    {
        foreach ($this->rolesLib::values() as $name) {
            $this->bar->advance();
            if (Role::whereSlug($name)->exists()) {
                continue;
            }
            $this->{$name} = new Role();
            $this->{$name}->slug = $name;
            $this->{$name}->assignable = in_array($name, $this->rolesLib::assignable());
            $this->{$name}->save();
        }
    }

    private function setPermissions(): void
    {
        foreach ($this->permissionsLib::nonassignable() as $slug => $roles) {
            $this->bar->advance();
            if (Permission::whereSlug($slug)->exists()) {
                continue;
            }
            $perm = new Permission();
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
            $perm = new Permission();
            $perm->slug = $slug;
            $perm->assignable = true;
            if ($perm->save()) {
                $this->attach($perm, $roles);
            }
        }
    }

    private function attach(Permission $permission, array $to): void
    {
        $roles = $this->rolesLib::values();

        foreach ($to as $slug) {
            if ('admins' === $slug) {
                foreach ($this->rolesLib::admins() as $role) {
                    $this->{$role}->permissions()->attach($permission);
                }
            } elseif ('*' === $slug) {
                foreach ($roles as $role) {
                    $this->{$role}->permissions()->attach($permission);
                }
            } else {
                $this->{$slug}->permissions()->attach($permission);
            }
        }
    }
}
