<?php

namespace App\Enums;

enum ListModuleEnums: string
{
    case BILLING = 'Billing';
    case PHARMACY = 'Pharmacy';
    case Inventory = 'Inventory';
    case Records  = 'Records';
    case Radiology = 'Radiology';
    case Service = 'Services';
    case Consultation = 'Consultant';
    case Laboratory = 'Laboratory';
    case Logs = 'Logs';
    case NURSE = 'Nurse';
}


// Records
// Services 
// Consultant
// Pharmacy 
// Laboratory 
// Radiology
// Billing
// Logs