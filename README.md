# Laravel Sentinel

Laravel Sentinel provides database-backed roles and permissions for Laravel, including permissions scoped to an Eloquent model context.

The current `main` branch supports PHP 8.3 or newer and Laravel 11, 12, and 13. This README documents the current development branch, whose registry-based API differs substantially from version `1.0.6`.

## Installation

Install the package, publish its migrations, and migrate the database:

```bash
composer require damianulan/laravel-sentinel:dev-main
php artisan vendor:publish --tag=sentinel-migrations
php artisan migrate
```

Publish the configuration only when the defaults need to be changed:

```bash
php artisan vendor:publish --tag=sentinel-config
```

Add `HasRolesAndPermissions` to each Eloquent model that receives roles or direct permissions:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Sentinel\Traits\HasRolesAndPermissions;

class User extends Authenticatable
{
    use HasRolesAndPermissions;
}
```

## Define roles and permissions

Sentinel discovers role and permission registries from Composer's optimized project classmap. An application or module may define multiple registries, but at least one implementation of each contract is required:

- `Sentinel\Contracts\RoleRegistryContract`
- `Sentinel\Contracts\PermissionRegistryContract`

Registries may be normal classes or backed enums. Slugs cannot contain whitespace.

### Permission registry

`permissions()` returns permission slugs keyed by their assignable state. The flag controls whether a permission appears in administrative select lists; it is not an authorization decision by itself.

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

`roles()` defines roles and their assignable state. `rolePermissions()` maps role slugs to permission slugs, while `adminRoles()` defines the roles recognized by `isAdmin()`.

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
            self::Administrator->value => ['*'],
            self::User->value => [
                PermissionRegistry::ViewUsers->value,
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

The `*` role mapping grants every registered permission. A registry may contribute permissions to a role declared by another registry, allowing modules to extend application-wide roles without redefining them.

Identical definitions from several registries are merged. Synchronization fails when registries disagree about an assignable state or reference an unknown role or permission.

## Synchronize the database

Refresh Composer's optimized classmap after adding, moving, or renaming a registry, then synchronize Sentinel:

```bash
composer dump-autoload --optimize
php artisan sentinel:run
```

The command runs in a database transaction and:

- discovers and validates all project registries;
- inserts or updates roles, permissions, and assignable states;
- synchronizes every role-permission pivot;
- removes database roles and permissions absent from the registries;
- invalidates all cached permission results after a successful commit;
- returns a failing exit code when discovery, validation, or synchronization fails.

> **Warning:** `sentinel:run` is authoritative. It deletes roles and permissions that are not declared by a discovered registry.

## Role API

Assign roles by slug, backed enum, or role model. Omit the context to use Sentinel's default system context:

```php
$user->assignRole(RoleRegistry::User);
$user->assignRole('administrator');
$user->assignRole($roleModel, $project);

$user->revokeRole(RoleRegistry::User);
$user->revokeRole($roleModel, $project);
```

Numeric primary keys use explicit methods:

```php
$user->assignRoleId($roleId);
$user->assignRoleId($roleId, $project);

$user->revokeRoleId($roleId);
$user->revokeRoleId($roleId, $project);
```

`assignRoleSlug()` and `revokeRoleSlug()` are deprecated aliases. Use `assignRole()` and `revokeRole()` instead.

Check roles by slug, backed enum, model, or numeric primary key:

```php
$user->hasRole(RoleRegistry::User);
$user->hasRole($roleId);

$user->hasAllRoles([
    RoleRegistry::User,
    RoleRegistry::Administrator,
]);

$user->hasRoles([
    RoleRegistry::User,
    RoleRegistry::Administrator,
]);

$user->hasAnyRoles([
    RoleRegistry::Administrator,
    RoleRegistry::Root,
]);
```

`hasRoles()` and `hasAllRoles()` require every role. `hasAnyRoles()` requires at least one. These methods search assignments from every context; use permission checks when access depends on a specific context.

Missing roles are logged as warnings. Checks return `false`, while assignment and revocation methods make no change.

### Synchronize assignable roles

```php
$user->refreshRole([
    RoleRegistry::User,
    RoleRegistry::Administrator,
]);
```

`refreshRole()` synchronizes only roles marked assignable in the default system context. It preserves non-assignable and context-specific assignments.

### Administrative roles

```php
$user->isAdmin();
$user->isRoot();
```

`isAdmin()` checks all roles declared by `adminRoles()` and also returns `true` for a root user. `isRoot()` checks the slug configured by `sentinel.root`.

## Direct permissions

Direct permission mutation methods accept slugs, backed enums, permission models, numeric primary keys, and nested arrays. `hasPermission()` accepts one slug, enum, model, or primary key:

```php
$user->givePermissionsTo(
    PermissionRegistry::ViewUsers,
    PermissionRegistry::UpdateUsers,
);

$user->hasPermission(PermissionRegistry::ViewUsers);

$user->deletePermissions(PermissionRegistry::UpdateUsers);

$user->refreshPermissions([
    PermissionRegistry::ViewUsers,
]);
```

`hasPermission()` checks direct assignments only. These mutation methods invalidate the subject's cached authorization results; prefer them over changing the `permissions()` relationship directly.

Missing permissions are logged as warnings. Checks return `false`, and mutation methods ignore definitions they cannot resolve.

## Context-aware authorization

Use `hasPermissionTo()` to include role permissions and optionally scope the check to an Eloquent model:

```php
$user->hasPermissionTo(PermissionRegistry::ViewUsers);
$user->hasPermissionTo(PermissionRegistry::UpdateUsers, $project);
```

Authorization behaves as follows:

| Check | Roles considered | Direct permissions |
| --- | --- | --- |
| `hasPermissionTo($permission)` | Roles from every context | Included |
| `hasPermissionTo($permission, $context)` | Roles assigned to that context plus default-system roles | Excluded |
| `hasPermissionThroughRole($permission, $context)` | Same context behavior as above | Excluded |

A root user passes every `hasPermissionTo()` check. Context models must be persisted; Sentinel stores their Eloquent morph class and primary key and throws a runtime exception for an unsaved context.

### Hierarchical wildcards

Wildcard permissions work in both directions:

- assigning `users.*` to a role or user grants descendant checks such as `users.view` and `users.profile.update`;
- checking `users.*` succeeds when the subject has any registered permission beginning with `users.`.

```php
$user->hasPermissionTo('users.view');
$user->hasPermissionTo('users.*');
```

Wildcard checks use the permission registry to expand the requested prefix. A wildcard database record is only needed when the wildcard itself should grant descendant permissions.

## Laravel Gate integration

Sentinel registers a `Gate::before()` hook:

```php
use Illuminate\Support\Facades\Gate;

$user->can(PermissionRegistry::UpdateUsers->value, $project);
Gate::allows(PermissionRegistry::ViewUsers->value);
```

For non-root users, Sentinel handles only exact abilities declared by a permission registry. Other abilities continue through normal Laravel gates and policies. Root users retain a global Gate bypass, including for abilities not registered with Sentinel.

Prefer Laravel authorization calls in policies, controllers, and middleware when the permission represents application access.

## Middleware

The service provider registers `sentinel.permission` and `sentinel.role` aliases:

```php
use Illuminate\Support\Facades\Route;

Route::get('/users', UsersController::class)
    ->middleware('sentinel.permission:users.view');

Route::put('/projects/{project}', UpdateProjectController::class)
    ->middleware('sentinel.permission:projects.update,project');

Route::get('/administration', AdministrationController::class)
    ->middleware('sentinel.role:administrator');
```

The optional second permission argument names a route parameter. Use implicit model binding so the middleware receives a persisted Eloquent context model. Failed checks return HTTP 403.

## Blade conditions

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

These conditions safely return `false` for unauthenticated users. Blade visibility is not authorization; protect the corresponding route or action on the server.

## Relationships and query scopes

The trait exposes `roles()`, `permissions()`, and `roleAssignments()`. Passing a context to `roles()` returns assignments from that context plus the default system context; calling it without a context returns assignments from every context.

Use the public query scopes to find authorizable models:

```php
User::query()
    ->withRole(RoleRegistry::Administrator)
    ->get();

User::query()
    ->withPermission(PermissionRegistry::ViewUsers)
    ->get();
```

`withPermission()` includes direct permissions and permissions inherited through roles. Query scopes accept slugs, backed enums, models, primary keys, nested arrays, and multiple arguments.

## Labels and select lists

`getRoles()` returns the subject's unique role slugs. `getRolesNames()` translates each slug using `gates.roles.<slug>` and falls back to the slug.

The built-in role and permission models expose assignable records as ordered select lists:

```php
use Sentinel\Models\Permission;
use Sentinel\Models\Role;

$roles = Role::getSelectList();
$permissions = Permission::getSelectList();
```

Labels use `gates.roles.<slug>` and `gates.permissions.<slug>` translation keys, falling back to their slugs.

## Caching

Sentinel caches permission results using:

- the authorizable model's morph type and primary key;
- the permission or wildcard expression;
- the optional context's morph type and primary key;
- a per-subject authorization version;
- a global authorization version.

Result identities are SHA-256 hashes, preventing collisions between morph classes, IDs, permission slugs, and contexts. Mutations through `HasRolesAndPermissions` rotate the affected subject's version. `sentinel:run` rotates the global version after its transaction commits. Old result entries become unreachable and expire normally.

```php
// config/sentinel.php
'cache' => [
    'enabled' => true,
    'driver' => 'default',
    'key' => 'sentinel.cache',
    'expire_after' => 86400,
],
```

- Set `enabled` to `false` or `expire_after` to `0` to bypass result caching.
- `driver` set to `default` uses the application's default cache store.
- Use a shared cache store when authorization changes and checks run on different application instances.
- Unsaved authorizable and context models are never cached.
- When pivot rows are changed outside Sentinel's mutation methods, invalidate the affected subject explicitly.

```php
use Sentinel\Cache\PermissionCache;

$cache = app(PermissionCache::class);

$cache->invalidate($user);
$cache->invalidateAll();
```

## Registry facade

The facade provides access to the discovered registry manifest. It is not installed as a global alias, so import it explicitly:

```php
use Sentinel\Facades\Sentinel;

$roles = Sentinel::roles();
$permissions = Sentinel::permissions();
$rolePermissions = Sentinel::rolePermissions();
$adminRoles = Sentinel::adminRoles();
```

The underlying service is `Sentinel\Registry\RegistryRepository` and may also be resolved from the container.

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

A custom default context must implement `Sentinel\Contracts\DefaultContext`. Custom role and permission classes must extend Eloquent `Model` and implement `RoleContract` or `PermissionContract`. Sentinel validates configured model classes before using them.

## Testing helper

`Sentinel\Testing\Concerns\InteractsWithRoles` adds a role assertion to a test case:

```php
use Sentinel\Testing\Concerns\InteractsWithRoles;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use InteractsWithRoles;

    public function test_it_assigns_the_administrator_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrator');

        $this->assertHasRole('administrator', $user);
    }
}
```

Use an isolated cache store such as Laravel's array store in tests that exercise permission caching.

## Upgrading from `1.0.6`

The current branch replaces the Warden-based API:

1. Replace the role and permission Warden classes with registries implementing `RoleRegistryContract` and `PermissionRegistryContract`.
2. Move role-permission mappings into `RoleRegistryContract::rolePermissions()` and administrative roles into `adminRoles()`.
3. Run `composer dump-autoload --optimize`, then `php artisan sentinel:run`.
4. Replace numeric calls to `assignRole()` and `revokeRole()` with `assignRoleId()` and `revokeRoleId()`.
5. Merge the new `cache.enabled` option into a published Sentinel configuration.
6. Review custom models against the reduced role and permission contracts.
7. Review already-published migrations separately; package migration fixes do not modify application migrations.

The old `SentinelManager`, Warden base classes, Warden exceptions, generator commands, and generator stubs have been removed.

## License

Laravel Sentinel is open-sourced software licensed under the [MIT license](LICENSE).
