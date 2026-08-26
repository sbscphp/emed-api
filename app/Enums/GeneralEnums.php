<?php

namespace App\Enums;

enum GeneralEnums: string
{
    case APPROVED = 'Approved';
    case PENDING = 'Pending';
    case DECLINED = 'Declined';
    case ACTIVE = 'Active';
    case INACTIVE = 'Inactive';
    case VERIFIED = 'Verified';
    case EXPIRED = 'Expired';
    case SETTLED = 'Settled';
    case COMPLETED = 'Completed';
    case NOT_DONE = 'Not done';
    case NOT_COMPLETED = 'Not completed';
    case PENDING_APPROVAL = 'Pending Approval';
    case NOT_ADMITTED = 'Not Admitted';
    case ADMITTED = 'Admitted';
    case DISCHARGED = 'Discharged';
    case DECEASED = 'Deceased';
    case NEW = "New";
    case EXISTING = "Existing";
    case FOLLOWUPPATIENT = "Follow Up";
    case NOT_READY = "Not Ready";
    case READY = "Ready";
    case FULLFILLED = "Fulfilled";
    case NOT_FULLFILLED = "Not Fulfilled";
    case AVAILABLE = "Available";
    case PAID = "Paid";
    case PART_PAID = "Part Paid";
    case IN_STOCK = "In stock";
    case OUT_OF_STOCK = "Out Of Stock";
    case LOW_STOCK = "Low Stock";
    case CANCELLED = "Cancelled";
    case ADMINISTERED = "Administered";
}
