<?php

namespace App\Enums;

enum CashbackStatus: string
{
    case PENDING = 'pending';
    case CREDITED = 'credited';
}
