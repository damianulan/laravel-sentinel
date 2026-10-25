<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Sentinel\Models\Permission;
use Sentinel\Models\Role;
use Sentinel\Registry\RegistryDiscovery;
use Sentinel\Registry\RegistryRepository;
use Tests\TestCase;

require_once dirname(__DIR__, 2) . '/Fixtures/Registries.php';

uses(TestCase::class);

function sentinelCommandRegistryRepository(): RegistryRepository
{
    return new RegistryRepository(new RegistryDiscovery(
        dirname(__DIR__, 2) . '/Fixtures/valid_classmap.php',
    ));
}

beforeEach(function (): void {
    if ( ! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('The PDO SQLite driver is required for this test.');
    }

    Schema::create('roles', function (Blueprint $table): void {
        $table->id();
        $table->string('slug')->unique();
        $table->boolean('assignable');
        $table->timestamps();
    });

    Schema::create('permissions', function (Blueprint $table): void {
        $table->id();
        $table->string('slug')->unique();
        $table->boolean('assignable');
        $table->timestamps();
    });

    Schema::create('roles_permissions', function (Blueprint $table): void {
        $table->foreignId('role_id');
        $table->foreignId('permission_id');
        $table->primary(['role_id', 'permission_id']);
    });

    $this->app->instance(RegistryRepository::class, sentinelCommandRegistryRepository());
});

afterEach(function (): void {
    if ( ! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        return;
    }

    Schema::dropIfExists('roles_permissions');
    Schema::dropIfExists('permissions');
    Schema::dropIfExists('roles');
});

it('synchronizes roles permissions and their associations from all registries', function (): void {
    Role::query()->create(['slug' => 'obsolete', 'assignable' => true]);
    Role::query()->create(['slug' => 'admin', 'assignable' => false]);
    Permission::query()->create(['slug' => 'obsolete', 'assignable' => true]);
    Permission::query()->create(['slug' => 'users.view', 'assignable' => false]);

    $this->artisan('sentinel:run')->assertExitCode(Command::SUCCESS);

    expect(Role::query()->orderBy('slug')->get()->mapWithKeys(
        fn (Role $role): array => [$role->slug => (bool) $role->assignable],
    )->all())->toBe([
        'admin' => true,
        'project-manager' => true,
        'root' => false,
    ])->and(Permission::query()->orderBy('slug')->get()->mapWithKeys(
        fn (Permission $permission): array => [$permission->slug => (bool) $permission->assignable],
    )->all())->toBe([
        'projects.view' => false,
        'users.view' => true,
    ])->and(Role::findBySlug('root')->permissions()->pluck('slug')->sort()->values()->all())->toBe([
        'projects.view',
        'users.view',
    ])->and(Role::findBySlug('admin')->permissions()->pluck('slug')->sort()->values()->all())->toBe([
        'projects.view',
        'users.view',
    ])->and(Role::findBySlug('project-manager')->permissions()->pluck('slug')->all())->toBe([
        'projects.view',
    ]);
});
