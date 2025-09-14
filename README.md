# Laravel Sentinel

[![Static Badge](https://img.shields.io/badge/made_with-Laravel-red?style=for-the-badge)](https://laravel.com/docs/11.x/releases) &nbsp; [![Licence](https://img.shields.io/github/license/Ileriayo/markdown-badges?style=for-the-badge)](./LICENSE) &nbsp; [![Static Badge](https://img.shields.io/badge/maintainer-damianulan-blue?style=for-the-badge)](https://damianulan.me)

## Description
Laravel roles and permission package, that exhausts contextual approach. Assign model as role's Context. When checking permissions, you can pass model instance as argument to verify if user has permission for that instance.

## Getting Started

### Installation

You can install the package via composer in your laravel project:
```
composer require damianulan/laravel-sentinel
```
and publish vendor assets:
```
php artisan vendor:publish --tag=sentinel
```

Then generate Permission and Role Sentinel libraries in your main project:
```
php artisan make:permissions
php artisan make:roles
```
Define your permissions and roles in those libraries as constants.

After that, in yout main User model, add `HasRolesAndPermissions` trait to your model.
```php
use Sentinel\Traits\HasRolesAndPermissions;

class User extends Authenticatable
{
    use HasRolesAndPermissions;
}
```

### Configuration

In `config/sentinel.php` you can configure package settings. It is important to set `uuids` option to true, if your models use UUIDs as primary keys.

Now migration can be run.
```
php artisan migrate
```

After running your package migration and tweaking your role and permission libraries run:
```
php artisan sentinel:run
```
This command will seed roles and permissions according to instructions you have defined in your libraries.
Use it each time you add new roles or permissions or make changes to existing ones.


## Examples

