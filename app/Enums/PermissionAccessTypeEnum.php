<?php

namespace App\Enums;

enum PermissionAccessTypeEnum: string
{
    case FULL = 'full';
    case PARTIAL = 'partial';
    case NONE = 'none';
}
