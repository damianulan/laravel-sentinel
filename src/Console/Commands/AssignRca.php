<?php

declare(strict_types=1);

namespace Sentinel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Sentinel\Cache\PermissionCache;
use Sentinel\Contracts\RoleContract;
use Sentinel\Helpers\SentinelHelper;
use Sentinel\Registry\RegistryManifest;
use Sentinel\Registry\RegistryRepository;
use Throwable;

final class AssignRca extends Command
{
    protected $signature = 'sentinel:run';

    protected $description = 'Synchronize registered Sentinel roles and permissions';

    public function __construct(
        private readonly RegistryRepository $registries,
        private readonly PermissionCache $permissionCache,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $manifest = $this->registries->manifest();

            DB::transaction(fn () => $this->synchronize($manifest));

            $this->permissionCache->invalidateAll();

            $this->components->info(sprintf(
                'Synchronized %d roles and %d permissions from %d registries.',
                count($manifest->roles),
                count($manifest->permissions),
                $manifest->registryCount(),
            ));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function synchronize(RegistryManifest $manifest): void
    {
        $this->upsertRoles($manifest->roles);
        $this->upsertPermissions($manifest->permissions);
        $this->deleteOutdatedRecords(array_keys($manifest->roles), array_keys($manifest->permissions));
        $this->syncRolePermissions($manifest->rolePermissions, array_keys($manifest->permissions));
    }

    /** @param array<string, bool> $roles */
    private function upsertRoles(array $roles): void
    {
        if ([] === $roles) {
            return;
        }

        SentinelHelper::getRoleClass()::query()->upsert(
            $this->records($roles),
            uniqueBy: ['slug'],
            update: ['assignable'],
        );
    }

    /** @param array<string, bool> $permissions */
    private function upsertPermissions(array $permissions): void
    {
        if ([] === $permissions) {
            return;
        }

        SentinelHelper::getPermissionClass()::query()->upsert(
            $this->records($permissions),
            uniqueBy: ['slug'],
            update: ['assignable'],
        );
    }

    /**
     * @param  array<string, bool>  $definitions
     * @return array<int, array{slug: string, assignable: bool}>
     */
    private function records(array $definitions): array
    {
        $records = [];

        foreach ($definitions as $slug => $assignable) {
            $records[] = compact('slug', 'assignable');
        }

        return $records;
    }

    /**
     * @param  array<string, array<int, string>>  $rolePermissions
     * @param  array<int, string>  $allPermissions
     */
    private function syncRolePermissions(array $rolePermissions, array $allPermissions): void
    {
        $permissionClass = SentinelHelper::getPermissionClass();
        $roleClass = SentinelHelper::getRoleClass();
        $permissionModel = SentinelHelper::newPermissionModel();
        $permissionIds = $permissionClass::query()->pluck($permissionModel->getKeyName(), 'slug');

        $roleClass::query()
            ->select([SentinelHelper::newRoleModel()->getKeyName(), 'slug'])
            ->each(function (RoleContract $role) use ($rolePermissions, $allPermissions, $permissionIds): void {
                $slug = $role->getAttribute('slug');

                if ( ! is_string($slug)) {
                    return;
                }

                $permissions = $rolePermissions[$slug] ?? [];

                if (in_array('*', $permissions, true)) {
                    $permissions = $allPermissions;
                }

                $role->permissions()->sync($permissionIds->only($permissions)->values()->all());
            });
    }

    /**
     * @param  array<int, string>  $roles
     * @param  array<int, string>  $permissions
     */
    private function deleteOutdatedRecords(array $roles, array $permissions): void
    {
        SentinelHelper::getPermissionClass()::query()->whereNotIn('slug', $permissions)->delete();
        SentinelHelper::getRoleClass()::query()->whereNotIn('slug', $roles)->delete();
    }
}
