<?php

declare(strict_types=1);

namespace Sentinel\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class SentinelProject extends Model
{
    protected $table = 'sentinel_test_projects';

    protected $guarded = [];
}
