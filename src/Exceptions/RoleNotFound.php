<?php

namespace Sentinel\Exceptions;

use Exception;

class RoleNotFound extends Exception
{
    public function __construct()
    {
        parent::__construct('Given role was not found!', 500);
    }
}
