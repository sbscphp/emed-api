<?php

namespace App\Enums;

enum PaymentChannelEnum: string
{
    case CARD = 'card';
    case TRANSFER = 'transfer';
    case CASH = 'cash';
    case POS = 'pos';
    case INSURANCE = 'insurance';
}
