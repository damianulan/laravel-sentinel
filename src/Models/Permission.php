<?php

namespace Sentinel\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Sentinel\Config\SentinelManager;
use Sentinel\Config\Warden\PermissionWarden;
use Sentinel\Exceptions\PermissionWardenException;
use Sentinel\Models\Role;

class Permission extends Model
{
    protected $table = 'permissions';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'slug',
        'assignable',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'roles_permissions');
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    public static function getSelectList(): array
    {
        $output = [];
        $permissionsLib = SentinelManager::getPermissionsLib();
        $permissions = self::where('assignable', 1)->get();
        if (! $permissions->isEmpty()) {
            foreach ($permissions as $permission) {
                $name = $permissionsLib::labels()[$permission->slug] ?? $permission->slug;
                $output[$permission->id] = $name;
            }
        }

        return $output;
    }

    public function scopeWhereSlug(Builder $query, string $slug): void
    {
        $query->where('slug', $slug);
    }

    public function scopeWhereAssignable(Builder $query): void
    {
        $query->where('assignable', 1);
    }

    public static function getPermissionsLib()
    {
        $value = SentinelManager::getPermissionsLibNamespace();

        $class = null;
        if (!empty($value)) {
            $class = new $value();
        }

        if (empty($value) || !($class instanceof PermissionWarden)) {
            throw new PermissionWardenException;
        }

        return $class;
    }
}
