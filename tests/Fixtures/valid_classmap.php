<?php

declare(strict_types=1);

use Sentinel\Tests\Fixtures\AbstractRoleRegistry;
use Sentinel\Tests\Fixtures\CorePermissionRegistry;
use Sentinel\Tests\Fixtures\CoreRoleRegistry;
use Sentinel\Tests\Fixtures\ProjectPermissionRegistry;
use Sentinel\Tests\Fixtures\ProjectRoleRegistry;

return [
    ProjectRoleRegistry::class => __DIR__ . '/Registries.php',
    CoreRoleRegistry::class => __DIR__ . '/Registries.php',
    AbstractRoleRegistry::class => __DIR__ . '/Registries.php',
    ProjectPermissionRegistry::class => __DIR__ . '/Registries.php',
    CorePermissionRegistry::class => __DIR__ . '/Registries.php',
];
