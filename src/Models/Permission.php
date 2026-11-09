<?php

declare(strict_types=1);

namespace Sentinel\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Sentinel\Contracts\PermissionContract;
use Sentinel\Contracts\RoleContract;
use Sentinel\Helpers\SentinelHelper;

/**
 * @property int $id
 * @property string $slug Permission shortname key.
 * @property bool $assignable Determines if permission is assignable throughout the platform.
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
    protected $table = 'permissions';

    protected $fillable = [
        'slug',
        'assignable',
    ];

    public static function findBySlug(string $slug): ?static
    {
        return static::query()->where('slug', $slug)->first();
    }

    /** @return array<int|string, string> */
    public static function getSelectList(): array
    {
        $query = static::query();
        $permissions = $query
            ->where('assignable', true)
            ->orderBy('slug')
            ->pluck('slug', $query->getModel()->getKeyName());
        $output = [];

        foreach ($permissions as $key => $slug) {
            if ( ! is_string($slug)) {
                continue;
            }

            $translationKey = "gates.permissions.{$slug}";
            $translation = __($translationKey);
            $output[$key] = Lang::has($translationKey) && is_string($translation)
                ? $translation
                : $slug;
        }

        return $output;
    }

    /** @return BelongsToMany<Model&RoleContract, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            SentinelHelper::getRoleClass(),
            'roles_permissions',
        );
    }

    /** @param Builder<static> $query */
    public function scopeWhereSlug(Builder $query, string ...$slugs): void
    {
        $query->whereIn('slug', $slugs);
    }

    /** @param Builder<static> $query */
    public function scopeWhereAssignable(Builder $query): void
    {
        $query->where('assignable', true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'assignable' => 'boolean',
        ];
    }
}
