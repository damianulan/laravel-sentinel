<?php

declare(strict_types=1);

namespace Sentinel\Exceptions;

use RuntimeException;

final class RoleNotFound extends RuntimeException
{
    public function __construct(string $role)
    {
        parent::__construct("The requested Sentinel role {$role} was not found.");
    }
}
