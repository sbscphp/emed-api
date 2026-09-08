<?php

namespace App\Enums;

/**
 * Role names seeded for every tenant. Kept as an enum so the patient role,
 * which is filtered out of the staff user list and granted by the patient
 * onboarding flow, is never spelled out as a loose string.
 */
enum RoleEnums: string
{
    case ADMIN = 'admin';
    case RECORD = 'record';
    case NURSE = 'nurse';
    case CONSULTANT = 'consultant';
    case PHARMACY = 'pharmacy';
    case LABORATORY = 'laboratory';
    case RADIOLOGY = 'radiology';
    case BILLING = 'billing';
    case PATIENT = 'patient';
}
