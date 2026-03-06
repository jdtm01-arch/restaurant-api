<?php

namespace App\Exceptions\Common;

use App\Exceptions\BusinessException;

class ResourceLockedException extends BusinessException
{
    public function __construct(string $message = 'Recurso bloqueado por cierre de caja.')
    {
        parent::__construct($message, 'RESOURCE_LOCKED', 422);
    }
}
