<?php

namespace Sentinel\Exceptions;

class PermissionWardenException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Permission Warden class is not declared in the project!', 500);
    }
}
