<?php

namespace Sentinel\Exceptions;

use Exception;

class RoleWardenException extends Exception
{
    public function __construct()
    {
        parent::__construct('Role Warden class is not declared in the project!', 500);
    }
}
