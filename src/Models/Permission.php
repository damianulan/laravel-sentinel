<?php

namespace Sentinel\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Sentinel\Config\SentinelManager;
use Sentinel\Config\Warden\PermissionWarden;
use Sentinel\Contracts\PermissionContract;
use Sentinel\Exceptions\PermissionWardenException;

/**
 * @property int $id
 * @property string $slug Permission shortname key.
 * @property int $assignable Determines if permission is assignable throughout the platform.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Role> $roles
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
class Permission extends Model implements PermissionContract
{
    public $timestamps = true;

    protected $table = 'permissions';

    protected $primaryKey = 'id';

    protected $fillable = [
        'slug',
        'assignable',
    ];

    public static function findBySlug(string $slug): ?static
    {
        return static::where('slug', $slug)->first();
    }

    public static function getSelectList(): array
    {
        $output = [];
        $permissionsLib = SentinelManager::getPermissionsLib();
        $permissions = self::where('assignable', 1)->get();
        if ( ! $permissions->isEmpty()) {
            foreach ($permissions as $permission) {
                $name = $permissionsLib::labels()[$permission->slug] ?? $permission->slug;
                $output[$permission->getKey()] = $name;
            }
        }

        return $output;
    }

    public static function getPermissionsLib()
    {
        $value = SentinelManager::getPermissionsLibNamespace();

        $class = null;
        if ( ! empty($value)) {
            $class = new $value();
        }

        if (empty($value) || ! ($class instanceof PermissionWarden)) {
            throw new PermissionWardenException();
        }

        return $class;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(config('sentinel.models.role'), 'roles_permissions');
    }

    public function scopeWhereSlug(Builder $query, ...$slugs): void
    {
        $query->whereIn('slug', $slugs);
    }

    public function scopeWhereAssignable(Builder $query): void
    {
        $query->where('assignable', 1);
    }
}
