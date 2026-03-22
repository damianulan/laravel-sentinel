# Laravel Sentinel

[![Laravel](https://img.shields.io/badge/made_with-Laravel-red?style=for-the-badge)](https://laravel.com) [![License](https://img.shields.io/badge/license-MIT-blue?style=for-the-badge)](LICENSE)

Laravel Sentinel is a context-aware roles and permissions package for Laravel.

Its core idea is simple:

- roles can be assigned globally or within a specific context
- permissions can be assigned directly to a user
- permissions can also be inherited through roles
- permission checks can optionally receive an Eloquent model instance as context

This makes it useful for applications where a user may have one role in one model context and a different role elsewhere, for example per-project, per-campaign, or per-team access control.

## Requirements

- PHP `^8.3`
- `illuminate/support` `^9.0|^10.0|^11.0|^12.0`

## Installation

Install the package:

```bash
composer require damianulan/laravel-sentinel
```

Publish the package assets:

```bash
php artisan vendor:publish --tag=sentinel
```

Available publish tags:

```bash
php artisan vendor:publish --tag=sentinel-config
php artisan vendor:publish --tag=sentinel-migrations
php artisan vendor:publish --tag=sentinel-langs
```

Run the published migrations:

```bash
php artisan migrate
```

Generate the two application warden classes used to define your platform's roles and permissions:

```bash
php artisan make:roles
php artisan make:permissions
```

Then synchronize those definitions into the database:

```bash
php artisan sentinel:run
```

Run `sentinel:run` every time you add, remove, or change role and permission definitions.

## How Sentinel Works

Sentinel has four main layers:

1. Warden classes in `App\Warden` define your canonical roles and permissions as constants and configuration arrays.
2. `sentinel:run` reads those classes and seeds the `roles`, `permissions`, and `roles_permissions` tables.
3. Your authenticatable model uses `Sentinel\Traits\HasRolesAndPermissions`.
4. Runtime checks use direct permissions, inherited role permissions, and optional model context.

## Database Structure

Published migrations create these tables:

- `roles`
- `permissions`
- `roles_permissions`
- `has_roles`
- `has_permissions`

Important pivots:

- `has_permissions` stores direct permission assignments per morphable model
- `has_roles` stores role assignments per morphable model and per morphable context

That means a user can have the same role multiple times, each attached to a different context.

## Setup

### 1. Add the trait to your user model

```php

namespace App\Models\Core;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Sentinel\Traits\HasRolesAndPermissions;

class User extends Authenticatable
{
    use HasRolesAndPermissions;
}
```

### 2. Define roles in `App\Warden`

Generated roles classes extend `Sentinel\Config\Warden\RoleWarden`.

Example:

```php

namespace App\Warden;

use Sentinel\Config\Warden\RoleWarden;

final class RolesLib extends RoleWarden
{
    public const ROOT = 'root';
    public const ADMIN = 'admin';
    public const PROJECT_MANAGER = 'project-manager';
    public const MEMBER = 'member';

    public static function assignable(): array
    {
        return [
            self::ADMIN,
            self::PROJECT_MANAGER,
            self::MEMBER,
        ];
    }

    public static function admins(): array
    {
        return [
            self::ROOT,
            self::ADMIN,
        ];
    }

    public static function labels(): array
    {
        return [
            self::ROOT => 'Root',
            self::ADMIN => 'Administrator',
            self::PROJECT_MANAGER => 'Project manager',
            self::MEMBER => 'Member',
        ];
    }
}
```

Key methods:

- `values()`: all declared constants plus optional extra items
- `assignable()`: roles allowed for platform assignment flows
- `admins()`: roles treated as admin roles by `isAdmin()`
- `labels()`: optional slug-to-label mapping

### 3. Define permissions in `App\Warden`

Generated permissions classes extend `Sentinel\Config\Warden\PermissionWarden`.

Example:

```php

namespace App\Warden;

use Sentinel\Config\Warden\PermissionWarden;

final class PermissionsLib extends PermissionWarden
{
    public const PROJECT_VIEW = 'project-view';
    public const PROJECT_UPDATE = 'project-update';
    public const PROJECT_DELETE = 'project-delete';
    public const USER_MANAGE = 'user-manage';

    public static function assignable(): array
    {
        return [
            self::PROJECT_VIEW => ['*'],
            self::PROJECT_UPDATE => [RolesLib::PROJECT_MANAGER, RolesLib::ADMIN],
            self::PROJECT_DELETE => ['admins'],
        ];
    }

    public static function nonassignable(): array
    {
        return [
            self::USER_MANAGE => ['admins'],
        ];
    }

    public static function labels(): array
    {
        return [
            self::PROJECT_VIEW => 'View projects',
            self::PROJECT_UPDATE => 'Update projects',
            self::PROJECT_DELETE => 'Delete projects',
            self::USER_MANAGE => 'Manage users',
        ];
    }
}
```

Meaning of the assignment arrays:

- `['*']`: attach permission to every role
- `['admins']`: attach permission to every role returned by `RolesLib::admins()`
- `[RolesLib::ADMIN, RolesLib::MEMBER]`: attach permission only to those explicit roles

Important distinction:

- `assignable()` creates permissions marked as assignable in the database
- `nonassignable()` creates permissions that still exist and can still be checked, but are not meant for regular UI assignment flows

### 4. Seed the definitions

Once your warden classes exist:

```bash
php artisan sentinel:run
```

What `sentinel:run` does:

- loads the role and permission wardens from `App\Warden`
- creates missing `roles`
- creates missing `permissions`
- attaches permissions to roles
- deletes outdated roles and permissions that no longer exist in the warden classes
- flushes Sentinel's internal cache

## Runtime API

The main runtime API lives on the model using `HasRolesAndPermissions`.

### Role relationships and assignment

Roles are assigned either:

- globally through the default context
- specifically to an Eloquent model instance used as the context

Example context model:

```php
use App\Models\Project;
use App\Warden\RolesLib;

$user->assignRoleSlug(RolesLib::PROJECT_MANAGER, $project);
$user->assignRoleSlug(RolesLib::ADMIN); // assigned in default context
```

You can also assign by role ID or role model:

```php
$user->assignRole($roleId, $project);
$user->assignRole($roleModel, $project);
```

Revoke role assignments:

```php
$user->revokeRoleSlug(RolesLib::PROJECT_MANAGER, $project);
$user->revokeRole($roleId, $project);
```

Inspect roles:

```php
$user->roles();           // all assigned roles relation
$user->roles($project);   // roles in project context + default system context
$user->roleAssignments(); // raw DB query for has_roles pivot rows

$user->getRoles();        // collection of role slugs
$user->getRolesNames();   // translated or labeled role names

$user->hasRole(RolesLib::ADMIN);
$user->hasRoles([RolesLib::ADMIN, RolesLib::MEMBER]);
$user->hasAnyRoles([RolesLib::ADMIN, RolesLib::PROJECT_MANAGER]);
```

### Direct permissions

Direct permissions are stored separately from role-based permissions.

```php
use App\Warden\PermissionsLib;

$user->givePermissionsTo(
    PermissionsLib::PROJECT_VIEW,
);

$user->deletePermissions(
    PermissionsLib::PROJECT_VIEW,
);

$user->refreshPermissions(
    PermissionsLib::PROJECT_VIEW,
    PermissionsLib::PROJECT_UPDATE,
);
```

Inspect direct permission assignments:

```php
$user->permissions();
$user->hasPermission(Permission::findBySlug(PermissionsLib::PROJECT_VIEW));
```

`hasPermission()` only checks direct permission assignments. It does not resolve inherited permissions or context.

### Permission checks

Use `hasPermissionTo()` for the normal application-facing check.

It supports:

- direct permissions
- permissions inherited through roles
- optional model context
- wildcard-style prefix matching using `permission-*`
- automatic root bypass

Examples:

```php
use App\Warden\PermissionsLib;

$user->hasPermissionTo(PermissionsLib::PROJECT_VIEW);
$user->hasPermissionTo(PermissionsLib::PROJECT_UPDATE, $project);
$user->hasPermissionTo('project-*', $project);
```

Behavior notes:

- If the user has the configured root role, `hasPermissionTo()` always returns `true`
- If a context is provided, Sentinel checks roles attached to that specific context and also roles attached to the default system context
- If no context is provided, Sentinel checks direct permissions and global role-derived permissions

### Admin and root helpers

```php
$user->isAdmin();
$user->isRoot();
```

- `isAdmin()` checks any role returned by your `RoleWarden::admins()` definition
- `isRoot()` checks the slug configured at `sentinel.root`

### Query scopes

The trait also adds scopes:

```php
User::query()->withRole(RolesLib::ADMIN)->get();
User::query()->withPermission(PermissionsLib::PROJECT_UPDATE)->get();
```

`withPermission()` matches both direct permissions and permissions inherited through roles.

## Contexts

Sentinel's distinguishing feature is context-aware role assignment.

The default context class is configured in `config/sentinel.php`:

```php
'default_context' => Sentinel\Contexts\System::class,
```

The built-in `System` context implements `Sentinel\Contracts\DefaultContext` and returns key `0`.

This means a role assigned without an explicit model context becomes a system-wide role:

```php
$user->assignRoleSlug(RolesLib::ADMIN);
```

To check permissions in a specific context:

```php
$user->hasPermissionTo(PermissionsLib::PROJECT_UPDATE, $project);
```

You can replace the default context class in config as long as it implements `Sentinel\Contracts\DefaultContext`.

## Gate Integration

Sentinel registers a `Gate::before()` callback in its service provider.

That means standard Laravel authorization checks can automatically resolve through `hasPermissionTo()` when the authenticated user exposes that method.

Examples:

```php
auth()->user()->can('project-update', $project);
auth()->user()->cannot('project-delete', $project);
```

The first Eloquent model found in the gate arguments is treated as the Sentinel context.

This makes Sentinel compatible with many normal Laravel authorization flows without writing a separate policy for every permission slug.

## Blade Directives

Sentinel registers three Blade conditionals:

```blade
@role('admin')
    <p>Visible to admins.</p>
@endrole

@admin
    <p>Visible to admin-class roles.</p>
@endadmin

@root
    <p>Visible only to the configured root role.</p>
@endroot
```

## Models

### `Sentinel\Models\Role`

Useful methods:

```php
use Sentinel\Models\Role;

$role = Role::findBySlug('admin');
$id = Role::getId('admin');
$assignable = Role::whereAssignable()->get();
$select = Role::getSelectList();
```

Relationships:

- `permissions()`

### `Sentinel\Models\Permission`

Useful methods:

```php
use Sentinel\Models\Permission;

$permission = Permission::findBySlug('project-update');
$assignable = Permission::whereAssignable()->get();
$select = Permission::getSelectList();
```

Relationships:

- `roles()`

## Configuration

Published config file: `config/sentinel.php`

Main options:

```php
return [
    'default_context' => Sentinel\Contexts\System::class,

    'models' => [
        'role' => Sentinel\Models\Role::class,
        'permission' => Sentinel\Models\Permission::class,
    ],

    'root' => 'root',

    'cache' => [
        'driver' => 'default',
        'key' => 'sentinel.cache',
        'expire_after' => 86400,
    ],
];
```

What these options control:

- `default_context`: global role context used when no model context is provided
- `models.role` / `models.permission`: override the Eloquent models used by Sentinel
- `root`: the role slug that bypasses all permission checks
- `cache.*`: cache used to store discovered Warden namespaces

## Cache Behavior

Sentinel caches the detected warden classes:

- `rolesLib`
- `permissionsLib`

These values are flushed automatically when `sentinel:run` completes.

If you move or rename your `App\Warden` classes and want to clear cache manually, rerun:

```bash
php artisan sentinel:run
```

## Middleware

The package includes middleware classes:

- `Sentinel\Http\Middleware\RoleMiddleware`
- `Sentinel\Http\Middleware\PermissionMiddleware`

Behavior:

- `RoleMiddleware` checks `Auth::user()->hasRole($role)`
- `PermissionMiddleware` checks `Auth::user()->cannot($permission, $context)`

Example registration in Laravel 11+:

```php
use Sentinel\Http\Middleware\PermissionMiddleware;
use Sentinel\Http\Middleware\RoleMiddleware;

->withMiddleware(function ($middleware): void {
    $middleware->alias([
        'role' => RoleMiddleware::class,
        'permission' => PermissionMiddleware::class,
    ]);
})
```

Example usage:

```php
Route::middleware('role:admin')->group(function () {
    // ...
});

Route::middleware('permission:project-update')->group(function () {
    // ...
});
```

If you need context-aware permission middleware, pass the appropriate argument pattern for your application or prefer standard Laravel gate checks inside controllers/services where the actual model instance is available.

## Testing Helper

The package includes a small testing concern:

```php
use Sentinel\Testing\Concerns\InteractsWithRoles;

uses(InteractsWithRoles::class);

it('assigns the admin role', function () use ($user) {
    $this->assertHasRole('admin', $user);
});
```

## Typical Example

A user can be a global admin and also a project-scoped manager:

```php
use App\Models\Project;
use App\Warden\PermissionsLib;
use App\Warden\RolesLib;

$project = Project::findOrFail(1);

$user->assignRoleSlug(RolesLib::ADMIN);
$user->assignRoleSlug(RolesLib::PROJECT_MANAGER, $project);

$user->hasPermissionTo(PermissionsLib::PROJECT_DELETE); // true if granted through admin role
$user->hasPermissionTo(PermissionsLib::PROJECT_UPDATE, $project); // true
```

Another project will not automatically inherit the project-scoped role:

```php
$otherProject = Project::findOrFail(2);

$user->hasPermissionTo(PermissionsLib::PROJECT_UPDATE, $otherProject); // false unless another role grants it
```

## Warden Discovery Rules

Sentinel looks for role and permission wardens under `App\Warden` by scanning Composer's autoload classmap and finding concrete subclasses of:

- `Sentinel\Config\Warden\RoleWarden`
- `Sentinel\Config\Warden\PermissionWarden`

If Sentinel cannot find one of these classes, it throws:

- `Role Warden class is not declared in the project!`
- `Permission Warden class is not declared in the project!`

If class discovery seems stale after adding or renaming warden classes, refresh Composer autoload metadata:

```bash
composer dump-autoload
php artisan sentinel:run
```

## Caveats

- Context applies to roles, not to direct permissions
- `hasPermission()` checks only direct permissions; use `hasPermissionTo()` for normal authorization decisions
- `sentinel:run` removes database roles and permissions that are no longer present in your warden definitions
- Middleware can only be context-aware when the relevant model instance is actually available to the middleware

## License

MIT. See [LICENSE](LICENSE).

## Contact

Questions and contributions: `damian.ulan@protonmail.com`
