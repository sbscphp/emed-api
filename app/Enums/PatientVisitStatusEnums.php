<?php

namespace App\Enums;

enum PatientVisitStatusEnums: string
{
    case WAITING = 'waiting';
    case ONGOING = 'ongoing';
    case COMPLETED = 'completed';
}
