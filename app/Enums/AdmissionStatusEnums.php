<?php

namespace App\Enums;

enum AdmissionStatusEnums: string
{
    case PENDING = 'Pending';
    case SCHEDULED = 'Scheduled';
    case ADMITTED = 'Admitted';
    case DISCHARGED = 'Discharged';
    case CANCELLED = 'Cancelled';

    /**
     * All statuses as plain strings.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
