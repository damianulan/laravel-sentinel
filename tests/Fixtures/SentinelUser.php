<?php

declare(strict_types=1);

namespace Sentinel\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Sentinel\Traits\HasRolesAndPermissions;

final class SentinelUser extends Model
{
    use HasRolesAndPermissions;

    protected $table = 'sentinel_test_users';

    protected $guarded = [];
}
