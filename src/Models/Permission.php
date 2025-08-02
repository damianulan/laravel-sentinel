<?php

namespace Sentinel\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Sentinel\Config\SentinelManager;
use Sentinel\Config\Warden\PermissionWarden;
use Sentinel\Exceptions\PermissionWardenException;

/**
 * @property int $id
 * @property string $slug
 * @property int $assignable
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Sentinel\Models\Role> $roles
 * @property-read int|null $roles_count
 *
 * @method static Builder<static>|Permission newModelQuery()
 * @method static Builder<static>|Permission newQuery()
 * @method static Builder<static>|Permission query()
 * @method static Builder<static>|Permission whereAssignable($value)
 * @method static Builder<static>|Permission whereCreatedAt($value)
 * @method static Builder<static>|Permission whereId($value)
 * @method static Builder<static>|Permission whereSlug($value)
 * @method static Builder<static>|Permission whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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
        if (! empty($value)) {
            $class = new $value;
        }

        if (empty($value) || ! ($class instanceof PermissionWarden)) {
            throw new PermissionWardenException;
        }

        return $class;
    }
}
