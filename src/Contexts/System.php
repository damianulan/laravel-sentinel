<?php

namespace Sentinel\Contexts;

use Sentinel\Contracts\DefaultContext;

class System implements DefaultContext
{
    public function getKey(): int
    {
        return 0;
    }
}
