<?php

namespace Sentinel\Exceptions;

use Exception;

class PermissionNotFound extends Exception
{
    public function __construct()
    {
        parent::__construct('Given permission was not found!', 500);
    }
}
