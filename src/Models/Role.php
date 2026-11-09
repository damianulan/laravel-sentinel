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
    protected $table = 'roles';

    protected $fillable = [
        'slug',
        'assignable',
    ];

    public static function findBySlug(string $slug): ?static
    {
        return static::query()->where('slug', $slug)->first();
    }

    public static function getId(string $slug): int|string|null
    {
        $query = static::query();
        $key = $query->where('slug', $slug)->value($query->getModel()->getKeyName());

        return is_int($key) || is_string($key) ? $key : null;
    }

    /** @return array<int|string, string> */
    public static function getSelectList(): array
    {
        $query = static::query();
        $roles = $query
            ->where('assignable', true)
            ->orderBy('slug')
            ->pluck('slug', $query->getModel()->getKeyName());
        $output = [];

        foreach ($roles as $key => $slug) {
            if ( ! is_string($slug)) {
                continue;
            }

            $translationKey = "gates.roles.{$slug}";
            $translation = __($translationKey);
            $output[$key] = Lang::has($translationKey) && is_string($translation)
                ? $translation
                : $slug;
        }

        return $output;
    }

    /** @return BelongsToMany<Model&PermissionContract, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            SentinelHelper::getPermissionClass(),
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
