<?php

declare(strict_types=1);

use Sentinel\Tests\Fixtures\ConflictingRoleRegistry;
use Sentinel\Tests\Fixtures\CorePermissionRegistry;
use Sentinel\Tests\Fixtures\CoreRoleRegistry;

return [
    CoreRoleRegistry::class => __DIR__ . '/Registries.php',
    ConflictingRoleRegistry::class => __DIR__ . '/Registries.php',
    CorePermissionRegistry::class => __DIR__ . '/Registries.php',
];
