<?php

namespace App\Enums;

enum PaymentGatewayEnum: string
{
    case PAYSTACK = 'paystack';
    case MANUAL = 'manual';
}
