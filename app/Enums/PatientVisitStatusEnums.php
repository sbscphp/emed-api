<?php

namespace App\Enums;

enum PatientVisitStatusEnums: string
{
    case VISIT_INITIATED = 'Visit Initiated';
    case WAITING = 'Waiting';
    case ONGOING = 'Ongoing';
    case COMPLETED = 'Completed';
}
