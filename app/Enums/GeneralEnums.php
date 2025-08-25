<?php

namespace App\Enums;

enum GeneralEnums: string
{
    case APPROVED = 'approved';
    case PENDING = 'pending';
    case DECLINED = 'declined';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case VERIFIED = 'verified';
    case EXPIRED = 'expired';
    case SETTLED = 'settled';
    case COMPLETED = 'completed';
    case NOT_DONE = 'not done';
    case NOT_COMPLETED = 'not completed';
    case PENDING_APPROVAL = 'pending-approval';
    case NOT_ADMITTED = 'Not-Admitted';
    case ADMITTED = 'Admitted';
    case DISCHARGED = 'Discharged';
    case DECEASED = 'Deceased';
    case NEWPATIENT = "New Patient";
    case FOLLOWUPPATIENT = "Follow Up";
}
