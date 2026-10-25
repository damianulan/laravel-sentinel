# Laravel Sentinel

Laravel Sentinel provides database-backed roles and permissions with optional model-specific contexts. It supports Laravel 11, 12, and 13 on PHP 8.3 or newer.

## Installation

```bash
composer require damianulan/laravel-sentinel
php artisan vendor:publish --tag=sentinel-migrations
php artisan migrate
```

Publish the configuration when the defaults need to be changed:

```bash
php artisan vendor:publish --tag=sentinel-config
```

Add the trait to the Eloquent model that receives roles and permissions:

```php
use Sentinel\Traits\HasRolesAndPermissions;

class User extends Authenticatable
{
    use HasRolesAndPermissions;
}
```

## Define registries

Sentinel discovers every concrete class or enum in the application's optimized Composer classmap that implements one of these contracts:

- `Sentinel\Contracts\RoleRegistryContract`
- `Sentinel\Contracts\PermissionRegistryContract`

An application or module may provide multiple registries. Identical definitions are merged; conflicting assignable states fail synchronization.

### Permission registry

The key is the permission slug. The boolean value determines whether the permission may be assigned directly to a user.

```php
namespace App\Authorization;

use Sentinel\Contracts\PermissionRegistryContract;

enum PermissionRegistry: string implements PermissionRegistryContract
{
    case ViewUsers = 'users.view';
    case UpdateUsers = 'users.update';
    case DeleteUsers = 'users.delete';

    public static function permissions(): array
    {
        return [
            self::ViewUsers->value => true,
            self::UpdateUsers->value => true,
            self::DeleteUsers->value => false,
        ];
    }
}
```

### Role registry

`roles()` maps role slugs to their assignable state. `rolePermissions()` maps roles to permission slugs. `adminRoles()` defines the roles recognized by `isAdmin()`.

```php
namespace App\Authorization;

use Sentinel\Contracts\RoleRegistryContract;

enum RoleRegistry: string implements RoleRegistryContract
{
    case Root = 'root';
    case Administrator = 'administrator';
    case User = 'user';

    public static function roles(): array
    {
        return [
            self::Root->value => false,
            self::Administrator->value => true,
            self::User->value => true,
        ];
    }

    public static function rolePermissions(): array
    {
        return [
            self::Root->value => ['*'],
            self::Administrator->value => [
                PermissionRegistry::ViewUsers->value,
                PermissionRegistry::UpdateUsers->value,
                PermissionRegistry::DeleteUsers->value,
            ],
        ];
    }

    public static function adminRoles(): array
    {
        return [
            self::Root->value,
            self::Administrator->value,
        ];
    }
}
```

The `*` association grants every registered permission to the role. A registry may attach permissions to a role declared by another registry, which lets modules contribute permissions to application-wide roles.

## Synchronize roles and permissions

Refresh Composer's optimized classmap after adding, moving, or renaming registries:

```bash
composer dump-autoload --optimize
php artisan sentinel:run
```

The command runs in a database transaction and:

- discovers and validates every project registry;
- bulk-upserts roles and permissions;
- updates assignable states;
- synchronizes role-permission pivots;
- deletes roles and permissions absent from all registries;
- invalidates every cached permission result after commit.

> **Warning:** `sentinel:run` is authoritative. Database roles and permissions not declared by a discovered registry are deleted.

## Roles

Registry-backed enums may be passed directly:

```php
$user->assignRole(RoleRegistry::User);
$user->assignRole(RoleRegistry::Administrator, $project);

$user->hasRole(RoleRegistry::User);
$user->hasRoles([RoleRegistry::User, RoleRegistry::Administrator]);
$user->hasAnyRoles([RoleRegistry::Administrator, RoleRegistry::Root]);

$user->revokeRole(RoleRegistry::User);
$user->revokeRole(RoleRegistry::Administrator, $project);
```

Integer primary keys, role model instances, and string slugs are also accepted.

`hasRole()` checks every context. Use permission checks when access depends on a specific context.

### Synchronizing assignable roles

```php
$user->refreshRole([
    RoleRegistry::User,
    RoleRegistry::Administrator,
]);
```

`refreshRole()` changes assignable roles in the default system context. Non-assignable roles are preserved.

### Administrative roles

```php
$user->isAdmin();
$user->isRoot();
```

`isAdmin()` checks `adminRoles()` from every role registry. `isRoot()` checks the slug configured by `sentinel.root`.

## Direct permissions

```php
$user->givePermissionsTo(
    PermissionRegistry::ViewUsers,
    PermissionRegistry::UpdateUsers,
);

$user->hasPermission(PermissionRegistry::ViewUsers);

$user->deletePermissions(PermissionRegistry::UpdateUsers);

$user->refreshPermissions(
    PermissionRegistry::ViewUsers,
);
```

These mutation methods invalidate the user's cached permission results. Prefer them over modifying the `permissions()` relationship directly.

## Context-aware permission checks

```php
$user->hasPermissionTo(PermissionRegistry::ViewUsers);
$user->hasPermissionTo(PermissionRegistry::UpdateUsers, $project);
```

Without a context, Sentinel grants access through a direct permission or any assigned role. With a context, it checks roles assigned to that model plus roles assigned to the default system context. Direct permissions are intentionally not applied to contextual checks.

Context models must be persisted before they are used. Sentinel stores their Eloquent morph class and primary key.

### Wildcards

A trailing `*` checks whether any registered permission begins with the preceding prefix:

```php
$user->hasPermissionTo('users.*');
```

Wildcard checks use registry definitions and do not require a wildcard database record.

## Laravel Gate integration

Sentinel registers a `Gate::before()` hook for registered permission slugs:

```php
$user->can(PermissionRegistry::UpdateUsers->value, $project);
Gate::allows(PermissionRegistry::ViewUsers->value);
```

Abilities not declared by a permission registry are left to normal Laravel gates and policies.

## Middleware

The service provider registers two middleware aliases:

```php
Route::get('/users', UsersController::class)
    ->middleware('sentinel.permission:users.view');

Route::put('/projects/{project}', UpdateProjectController::class)
    ->middleware('sentinel.permission:projects.update,project');

Route::get('/administration', AdministrationController::class)
    ->middleware('sentinel.role:administrator');
```

The optional second permission-middleware argument is the name of a route parameter. Use implicit model binding so Sentinel receives the context model.

## Blade

```blade
@role('administrator')
    ...
@endrole

@admin
    ...
@endadmin

@root
    ...
@endroot
```

Blade visibility is not authorization. Protect the corresponding route or action on the server.

## Query scopes

Models using `HasRolesAndPermissions` expose:

```php
User::query()->withRole(RoleRegistry::Administrator)->get();
User::query()->withPermission(PermissionRegistry::ViewUsers)->get();
```

## Caching

Permission results are cached by:

- authorizable morph type and primary key;
- permission or wildcard expression;
- optional context morph type and primary key;
- a per-authorizable version;
- a global authorization version.

Keys use SHA-256 identities, avoiding collisions between slugs, morph classes, and context keys. Per-user version entries expire after cached results can no longer exist, while only the single global version is stored indefinitely.

Role or direct-permission mutations through `HasRolesAndPermissions` rotate that user's version. `sentinel:run` rotates the global version. Old result entries become unreachable and expire normally, so cache tags and full-store flushes are unnecessary.

```php
// config/sentinel.php
'cache' => [
    'enabled' => true,
    'driver' => 'default',
    'key' => 'sentinel.cache',
    'expire_after' => 86400,
],
```

- Set `enabled` to `false` or `expire_after` to `0` to bypass caching.
- `driver: default` uses the application's default cache store.
- Use a shared cache store when authorization mutations and checks run on different application instances.
- If role or permission pivots are changed outside Sentinel's mutation methods, explicitly invalidate the affected subject or avoid bypassing the package API.

```php
use Sentinel\Cache\PermissionCache;

$cache = app(PermissionCache::class);

$cache->invalidate($user);
$cache->invalidateAll();
```

Unsaved authorizable and context models are never cached.

## Configuration

```php
return [
    'default_context' => Sentinel\Contexts\System::class,

    'models' => [
        'role' => Sentinel\Models\Role::class,
        'permission' => Sentinel\Models\Permission::class,
    ],

    'root' => 'root',

    'cache' => [
        'enabled' => true,
        'driver' => 'default',
        'key' => 'sentinel.cache',
        'expire_after' => 86400,
    ],
];
```

A custom default context must implement `Sentinel\Contracts\DefaultContext`. Custom role and permission models must extend Eloquent `Model` and implement `RoleContract` or `PermissionContract`.

## Testing helpers

`Sentinel\Testing\Concerns\InteractsWithRoles` provides:

```php
$this->assertHasRole('administrator', $user);
```

Use the array cache driver in tests to keep permission checks isolated.

## License

Laravel Sentinel is open-sourced software licensed under the [MIT license](LICENSE).
