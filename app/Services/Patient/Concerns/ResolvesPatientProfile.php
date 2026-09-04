<?php

namespace App\Services\Patient\Concerns;

use App\Models\Patient;
use App\Models\TenantUser;
use Carbon\Carbon;

/**
 * Reading the patient details that are kept in two places at once.
 *
 * A date of birth is recorded twice. `tenant_users.date_of_birth` on the
 * landlord is what the account was registered with; `patients.dob` in the
 * hospital's own database is what its records hold, and is the one the patient
 * edits from the app. Nothing has ever kept the two in step, so a patient
 * registered through one form and filed through another ends up with a date on
 * the account and none on the record — which is exactly how the login response
 * came to show a date of birth while the Personal Information screen showed
 * null for the same patient.
 *
 * This resolves that the same way everywhere it is asked: the hospital's record
 * is the answer when it has one, and the account is the fallback when it does
 * not. Neither screen can then disagree with the other.
 *
 * Writing is handled at the point of the write — see
 * PatientProfileService::updatePersonalInformation(), which now saves a new date
 * to both, so the gap closes rather than being papered over on every read.
 */
trait ResolvesPatientProfile
{
    /**
     * The patient's date of birth, from whichever store holds one.
     *
     * @return string|null as Y-m-d, or null when neither store has it
     */
    protected function resolveDob(?Patient $patient, ?TenantUser $tenantUser = null): ?string
    {
        return $this->toDate($patient?->dob)
            ?? $this->toDate($tenantUser?->date_of_birth);
    }

    /**
     * A date formatted for display, tolerant of whatever shape it was stored in.
     *
     * The columns behind these are plain strings on models that cast nothing, so
     * anything from '2000-05-20' to a full timestamp can arrive here.
     */
    protected function toDate($value, string $format = 'Y-m-d'): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format($format);
        } catch (\Throwable $th) {
            return null;
        }
    }
}
