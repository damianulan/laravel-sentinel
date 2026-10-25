<?php

declare(strict_types=1);

namespace Sentinel\Exceptions;

use RuntimeException;

final class PermissionNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The requested Sentinel permission was not found.');
    }
}
