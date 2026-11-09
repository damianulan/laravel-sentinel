<?php

declare(strict_types=1);

use Sentinel\Contexts\System;
use Sentinel\Models\Permission;
use Sentinel\Models\Role;

return [
    'default_context' => System::class,

    'models' => [
        'role' => Role::class,
        'permission' => Permission::class,
    ],

    'root' => 'root',

    'cache' => [
        'enabled' => true,

        'driver' => 'default',

        'key' => 'sentinel.cache',

        'expire_after' => 86400,
    ],
];
