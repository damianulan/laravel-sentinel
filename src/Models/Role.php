<?php

namespace Sentinel\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Sentinel\Config\SentinelManager;
use Sentinel\Config\Warden\RoleWarden;
use Sentinel\Contracts\RoleContract;
use Sentinel\Exceptions\RoleWardenException;

/**
 * @property int $id
 * @property string $slug Role shortname key.
 * @property bool $assignable Determines if role is assignable throughout the platform.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Permission> $permissions
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
class Role extends Model implements RoleContract
{
    public $timestamps = true;

    protected $table = 'roles';

    protected $primaryKey = 'id';

    protected $fillable = [
        'slug',
        'assignable',
    ];

    protected $casts = [
        'assignable' => 'boolean',
    ];

    public static function findBySlug(string $slug): ?static
    {
        return self::whereSlug($slug)->first();
    }

    public static function getId(string $slug): ?string
    {
        $role = self::whereSlug($slug)->first();
        if ($role && null !== $role->getKey()) {
            return $role->getKey();
        }

        return null;
    }

    public static function getSelectList(): array
    {
        $output = [];
        $roles = self::where('assignable', 1)->get();
        if ( ! $roles->isEmpty()) {
            foreach ($roles as $role) {
                $name = __('gates.roles.' . $role->slug);
                $output[$role->id] = $name;
            }
        }

        return $output;
    }

    public static function getRolesLib()
    {
        $value = SentinelManager::getRolesLibNamespace();

        $class = null;
        if ( ! empty($value)) {
            $class = new $value();
        }

        if (empty($value) || ! ($class instanceof RoleWarden)) {
            throw new RoleWardenException();
        }

        return $class;
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(config('sentinel.models.permission'), 'roles_permissions');
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
