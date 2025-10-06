<?php

namespace App\Enums;

enum PermissionAccessTypeEnum: string
{
    case FULL = 'Full';
    case PARTIAL = 'Partial';
    case NONE = 'None';
}
