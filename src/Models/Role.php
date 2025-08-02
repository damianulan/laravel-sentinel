<?php

namespace Sentinel\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Sentinel\Config\SentinelManager;
use Sentinel\Config\Warden\RoleWarden;
use Sentinel\Exceptions\RoleWardenException;

/**
 * @property int $id
 * @property string $slug
 * @property bool $assignable
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Sentinel\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 *
 * @method static Builder<static>|Role newModelQuery()
 * @method static Builder<static>|Role newQuery()
 * @method static Builder<static>|Role query()
 * @method static Builder<static>|Role whereAssignable($value)
 * @method static Builder<static>|Role whereCreatedAt($value)
 * @method static Builder<static>|Role whereId($value)
 * @method static Builder<static>|Role whereSlug($value)
 * @method static Builder<static>|Role whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Role extends Model
{
    protected $table = 'roles';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'slug',
        'assignable',
    ];

    protected $casts = [
        'assignable' => 'boolean',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'roles_permissions');
    }

    public static function findBySlug(string $slug): ?self
    {
        return self::whereSlug($slug)->first();
    }

    public static function getId(string $slug): ?string
    {
        $role = self::whereSlug($slug)->first();
        if (isset($role->id)) {
            return $role->id;
        }

        return null;
    }

    public static function getSelectList(): array
    {
        $output = [];
        $roles = self::where('assignable', 1)->get();
        if (! $roles->isEmpty()) {
            foreach ($roles as $role) {
                $name = __('gates.roles.'.$role->slug);
                $output[$role->id] = $name;
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

    public static function getRolesLib()
    {
        $value = SentinelManager::getRolesLibNamespace();

        $class = null;
        if (! empty($value)) {
            $class = new $value;
        }

        if (empty($value) || ! ($class instanceof RoleWarden)) {
            throw new RoleWardenException;
        }

        return $class;
    }
}
