<?php

namespace Sentinel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Sentinel\Config\SentinelManager;
use Sentinel\Models\Role;

class Permission extends Model
{
    use HasFactory;

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

    public static function getBySlug(string $slug): ?self
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
}
