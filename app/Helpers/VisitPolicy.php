<?php

namespace App\Helpers;

use App\Enums\GeneralEnums;
use App\Enums\PatientVisitStatusEnums;
use App\Models\AdmittedPatient;
use App\Models\PatientVisit;
use Carbon\Carbon;

/**
 * Day-scoped visit rules. Outpatient visits belong to a single day; once the day
 * passes they must be closed and a new visit initiated. Admitted/inpatient visits
 * legitimately span days and are exempt.
 */
class VisitPolicy
{
    /** An admission (not discharged) tied to this visit makes it inpatient. */
    public static function isInpatient(PatientVisit $visit): bool
    {
        return AdmittedPatient::where('visit_id', $visit->id)
            ->where('status', '!=', GeneralEnums::DISCHARGED->value)
            ->exists();
    }

    /** Open outpatient visit whose day has already passed. */
    public static function isStaleOutpatient(PatientVisit $visit): bool
    {
        if ($visit->status === PatientVisitStatusEnums::COMPLETED->value) {
            return false;
        }

        $day = $visit->arrival_date ?? $visit->created_at;
        if (!$day || !Carbon::parse($day)->lt(Carbon::today())) {
            return false;
        }

        return !self::isInpatient($visit);
    }

    /** Close the visit if it is a stale outpatient visit. Returns true if closed. */
    public static function closeStaleOutpatient(PatientVisit $visit): bool
    {
        if (!self::isStaleOutpatient($visit)) {
            return false;
        }

        $visit->update([
            'status'         => PatientVisitStatusEnums::COMPLETED->value,
            'departure_date' => now(),
        ]);

        return true;
    }
}
