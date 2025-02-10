<?php

namespace App\Enums;

enum ModuleEnums: string
{
    case CUSTOMER = 'customer';
    case GUEST = 'guest';
    case SUPER_ADMIN = 'super admin';
    case ADMIN = 'admin';
}
