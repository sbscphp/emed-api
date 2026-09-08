<?php

namespace App\Enums;

enum AppointmentStatusEnums: string
{
    case SCHEDULED = 'Scheduled';
    case CHECKED_IN = 'Checked In';
    case COMPLETED = 'Completed';
    case CANCELED = 'Canceled';
    case NO_SHOW = 'No show';
}
