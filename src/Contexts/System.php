<?php

declare(strict_types=1);

namespace Sentinel\Contexts;

use Sentinel\Contracts\DefaultContext;

final class System implements DefaultContext
{
    public function getKey(): int
    {
        return 0;
    }
}
