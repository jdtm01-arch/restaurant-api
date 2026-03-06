<?php

namespace App\Exceptions\CashClosing;

use App\Exceptions\BusinessException;

class ClosingAlreadyExistsException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            'Ya existe un cierre contable para esta fecha.',
            'CLOSING_ALREADY_EXISTS',
            409
        );
    }
}
