<?php

namespace Sentinel\Contracts;

interface DefaultContext
{
    /**
     * Get default context key.
     * Usually its best to return 0.
     */
    public function getKey(): int;
}
