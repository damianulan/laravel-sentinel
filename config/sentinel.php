<?php

use Sentinel\Contexts\System;
use Sentinel\Models\Permission;
use Sentinel\Models\Role;

return [
    'default_context' => System::class,

    'wardens' => [
        'roles' => "App\Warden\RolesLib",
        'permissions' => "App\Warden\PermissionsLib",
    ],

    'models' => [
        'role' => Role::class,
        'permission' => Permission::class,
    ],

    'root' => 'root',

    'cache' => [
        'driver' => 'default',

        'key' => 'sentinel.cache',

        'expire_after' => 86400,
    ],
];
