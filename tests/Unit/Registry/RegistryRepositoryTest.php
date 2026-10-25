<?php

declare(strict_types=1);

use Sentinel\Contracts\PermissionRegistryContract;
use Sentinel\Contracts\RoleRegistryContract;
use Sentinel\Registry\RegistryDiscovery;
use Sentinel\Registry\RegistryRepository;
use Sentinel\Tests\Fixtures\CorePermissionRegistry;
use Sentinel\Tests\Fixtures\CoreRoleRegistry;
use Sentinel\Tests\Fixtures\ProjectPermissionRegistry;
use Sentinel\Tests\Fixtures\ProjectRoleRegistry;

require_once dirname(__DIR__, 2) . '/Fixtures/Registries.php';

function sentinelRegistryRepository(string $classMap = 'valid_classmap.php'): RegistryRepository
{
    return new RegistryRepository(new RegistryDiscovery(
        dirname(__DIR__, 2) . "/Fixtures/{$classMap}",
    ));
}

it('discovers every concrete class and enum implementing registry contracts', function (): void {
    $discovery = new RegistryDiscovery(dirname(__DIR__, 2) . '/Fixtures/valid_classmap.php');

    expect($discovery->implementing(RoleRegistryContract::class))->toBe([
        CoreRoleRegistry::class,
        ProjectRoleRegistry::class,
    ])->and($discovery->implementing(PermissionRegistryContract::class))->toBe([
        CorePermissionRegistry::class,
        ProjectPermissionRegistry::class,
    ]);
});

it('ignores dependency classes outside the project path', function (): void {
    $projectPath = dirname(__DIR__, 6);
    $discovery = new RegistryDiscovery(
        dirname(__DIR__, 2) . '/Fixtures/valid_classmap.php',
        $projectPath,
    );

    expect($discovery->implementing(RoleRegistryContract::class))->toBe([])
        ->and($discovery->implementing(PermissionRegistryContract::class))->toBe([]);
});

it('merges definitions and associations from multiple registries', function (): void {
    $registries = sentinelRegistryRepository();

    expect($registries->roles())->toBe([
        'root' => false,
        'admin' => true,
        'project-manager' => true,
    ])->and($registries->permissions())->toBe([
        'users.view' => true,
        'projects.view' => false,
    ])->and($registries->rolePermissions())->toBe([
        'root' => ['*'],
        'admin' => ['users.view', 'projects.view'],
        'project-manager' => ['projects.view'],
    ])->and($registries->adminRoles())->toBe(['admin']);
});

it('rejects conflicting definitions from different registries', function (): void {
    sentinelRegistryRepository('conflicting_classmap.php')->roles();
})->throws(InvalidArgumentException::class, 'conflicts with another definition');
