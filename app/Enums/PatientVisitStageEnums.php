<?php

namespace App\Enums;

enum PatientVisitStageEnums: string
{
    case VISIT = 'Visit';
    case TRIAGE = 'Triage';
    case CONSULTATION = 'Consultation';
    case INVESTIGATION = 'Investigation';
    case ADMITTED = 'Admitted';
    case TREATMENT = 'Treatment';
    case DISCHARGED = 'Discharged';
}
