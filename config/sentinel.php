<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sentinel Configuration file v1.0
    |--------------------------------------------------------------------------
    |
    | These are package's default configuration options.
    |
    */

    /**
     * choose whether role assigned models use uuids or not.
     * YOU SHOULD DECIDE ON THIS BEFORE MIGRATION
     */
    'uuids' => false,

    /**
     * Choose default context for Sentinel. This context will be used when
     * creating new roles and permissions that don't have context specified.
     */
    'default_context' => Sentinel\Contexts\System::class,

    /**
     * Sentinel uses those models to determine Eloquent relations.
     * If you want to use your own models, you can specify them here.
     * Your custom models must implement Sentinel contracts.
     */
    'models' => [
        'role' => Sentinel\Models\Role::class,
        'permission' => Sentinel\Models\Permission::class,
    ],

    /**
     * Here pass role slug that will be assigned with root privileges.
     * User with this role will pass all role and permission checks.
     */
    'root' => 'root',

    /**
     * Sentinel uses cache to store roles and permissions datas in order to speed up
     * roles and permissions loading. You can change cache prefix here and cache driver.
     */
    'cache' => [
        /*
         * You may optionally indicate a specific cache driver to use for permission and
         * role caching using any of the `store` drivers listed in the cache.php config
         * file. Using 'default' here means to use the `default` set in cache.php.
         */
        'driver' => 'default',

        /**
         * Senitnel cache standard prefix.
         */
        'key' => 'sentinel.cache',

        /**
         * Determines how long to keep the permission cache. (in seconds).
         * Cache is automatically cleared when artisan sentinel:run command is executed.
         */
        'expire_after' => 86400,
    ],

];
