<?php

namespace App\Exceptions\Order;

use App\Exceptions\BusinessException;

class InvalidChannelTableException extends BusinessException
{
    public function __construct(string $message = 'Combinación canal/mesa inválida. dine_in requiere mesa, takeaway no.')
    {
        parent::__construct($message, 'INVALID_CHANNEL_TABLE', 422);
    }
}
