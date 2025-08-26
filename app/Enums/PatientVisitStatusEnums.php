<?php

namespace App\Enums;

enum PatientVisitStatusEnums: string
{
    case VISIT_INITIATED = 'Visit Initiated';
    case TRIAGE = 'Triage';
    case CONSULTATION = 'Consultation';
    case INVESTIGATION = 'Investigation';
    case TREATMENT = 'Treatment';
    case ADMITTED = 'Admitted';
    case DISCHARGED = 'Discharged';
    case WAITING = 'Waiting';
    case ONGOING = 'Ongoing';
    case COMPLETED = 'Completed';
}
