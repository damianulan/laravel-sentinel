<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Sentinel\Models\Permission;
use Sentinel\Models\Role;
use Sentinel\Tests\Fixtures\SentinelProject;
use Sentinel\Tests\Fixtures\SentinelUser;
use Tests\TestCase;

require_once dirname(__DIR__, 2) . '/Fixtures/SentinelProject.php';
require_once dirname(__DIR__, 2) . '/Fixtures/SentinelUser.php';

uses(TestCase::class);

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
    Schema::create('sentinel_test_users', function (Blueprint $table): void {
        $table->id();
        $table->timestamps();
    });
    Schema::create('sentinel_test_projects', function (Blueprint $table): void {
        $table->id();
        $table->timestamps();
    });
    Schema::create('has_roles', function (Blueprint $table): void {
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->foreignId('role_id');
        $table->string('context_type');
        $table->unsignedBigInteger('context_id');
        $table->primary(['model_type', 'model_id', 'role_id', 'context_type', 'context_id']);
    });
    Schema::create('has_permissions', function (Blueprint $table): void {
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->foreignId('permission_id');
        $table->primary(['model_type', 'model_id', 'permission_id']);
    });
});

afterEach(function (): void {
    if ( ! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        return;
    }

    Schema::dropIfExists('has_permissions');
    Schema::dropIfExists('has_roles');
    Schema::dropIfExists('sentinel_test_projects');
    Schema::dropIfExists('sentinel_test_users');
    Schema::dropIfExists('roles_permissions');
    Schema::dropIfExists('permissions');
    Schema::dropIfExists('roles');
});

it('revokes a contextual role without affecting other contexts and invalidates cached checks', function (): void {
    $role = Role::query()->create(['slug' => 'project-manager', 'assignable' => true]);
    $permission = Permission::query()->create(['slug' => 'projects.view', 'assignable' => false]);
    $role->permissions()->attach($permission);
    $user = SentinelUser::query()->create();
    $firstProject = SentinelProject::query()->create();
    $secondProject = SentinelProject::query()->create();

    $user->assignRole('project-manager', $firstProject);
    $user->assignRole('project-manager', $secondProject);

    expect($user->hasPermissionTo('projects.view', $firstProject))->toBeTrue()
        ->and($user->hasPermissionTo('projects.view', $secondProject))->toBeTrue();

    $user->revokeRole('project-manager', $firstProject);

    expect($user->hasPermissionTo('projects.view', $firstProject))->toBeFalse()
        ->and($user->hasPermissionTo('projects.view', $secondProject))->toBeTrue()
        ->and($user->roleAssignments()->count())->toBe(1);
});

it('grants every permission below a directly assigned wildcard namespace', function (): void {
    Permission::query()->create(['slug' => 'users.*', 'assignable' => true]);
    Permission::query()->create(['slug' => 'users.create', 'assignable' => true]);
    Permission::query()->create(['slug' => 'users.update', 'assignable' => true]);
    Permission::query()->create(['slug' => 'projects.update', 'assignable' => true]);
    $user = SentinelUser::query()->create();

    $user->givePermissionsTo('users.*');

    expect($user->hasPermission('users.create'))->toBeTrue()
        ->and($user->hasPermissionTo('users.update'))->toBeTrue()
        ->and(SentinelUser::query()->withPermission('users.update')->whereKey($user->getKey())->exists())->toBeTrue()
        ->and($user->hasPermissionTo('projects.update'))->toBeFalse();
});

it('grants every permission below a wildcard namespace through a contextual role', function (): void {
    $role = Role::query()->create(['slug' => 'user-manager', 'assignable' => true]);
    $wildcardPermission = Permission::query()->create(['slug' => 'users.*', 'assignable' => true]);
    Permission::query()->create(['slug' => 'users.profile.update', 'assignable' => true]);
    Permission::query()->create(['slug' => 'projects.update', 'assignable' => true]);
    $role->permissions()->attach($wildcardPermission);
    $user = SentinelUser::query()->create();
    $project = SentinelProject::query()->create();
    $otherProject = SentinelProject::query()->create();

    $user->assignRole('user-manager', $project);

    expect($user->hasPermissionThroughRole('users.profile.update', $project))->toBeTrue()
        ->and($user->hasPermissionTo('users.profile.update', $project))->toBeTrue()
        ->and($user->hasPermissionTo('users.profile.update', $otherProject))->toBeFalse()
        ->and($user->hasPermissionTo('projects.update', $project))->toBeFalse();
});
