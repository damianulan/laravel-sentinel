<?php

declare(strict_types=1);

namespace Sentinel\Facades;

use Illuminate\Support\Facades\Facade;
use Sentinel\Registry\RegistryRepository;

final class Sentinel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RegistryRepository::class;
    }
}
