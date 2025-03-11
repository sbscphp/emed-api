<?php

namespace App\Enums;

enum PatientVisitStageEnums: string
{
    case TRIAGE = 'triage';
    case CONSULTATION = 'consultation';
    case INVESTIGATION = 'investigation';
    case ADMITTED = 'admitted';
    case TREATMENT = 'treatment';
    case DISCHARGED = 'discharged';
}
