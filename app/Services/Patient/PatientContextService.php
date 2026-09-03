<?php

namespace App\Services\Patient;

use App\Enums\RoleEnums;
use App\Exceptions\PatientAppException;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Class PatientContextService
 *
 * Answers the one question every signed in patient app request starts with:
 * which hospital is this about, and which patient record does the caller hold
 * there?
 *
 * A patient account is shared across the hospitals that registered it, and each
 * hospital keeps its own patient record in its own database. So the account
 * alone identifies nobody — it only becomes a patient once paired with the
 * hospital carried in the X-Tenant-ID header. Every module of the app needs
 * that pairing before it can read or write anything, which is why it is
 * resolved here rather than in each service.
 *
 * Results are memoised per request: a controller action may ask for the patient
 * several times over, and neither lookup should be repeated.
 */
class PatientContextService
{
    protected ?Tenant $tenant = null;

    protected ?Patient $patient = null;

    /**
     * The hospital the current request is about.
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function tenant(): Tenant
    {
        if ($this->tenant) {
            return $this->tenant;
        }

        $tenantUuid = request()->header('X-Tenant-ID');

        if (empty($tenantUuid)) {
            throw new PatientAppException('Please select a hospital to continue.', 400);
        }

        $tenant = Tenant::where('uuid', $tenantUuid)->first();

        if (!$tenant) {
            throw new PatientAppException('Invalid hospital selected.', 404);
        }

        // The tenant middleware has normally done this already; repeated here so
        // the service is also safe to call from a queued job or a command, where
        // no middleware ran.
        $tenant->makeCurrent();

        return $this->tenant = $tenant;
    }

    /**
     * The signed in account.
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function user(): User
    {
        $user = Auth::user();

        if (!$user) {
            throw new PatientAppException('Please sign in to continue.', 401);
        }

        return $user;
    }

    /**
     * The patient record the signed in account holds at the current hospital.
     *
     * Both halves are checked: the account has to be a member of the hospital
     * with the patient role, and the hospital has to hold a record for it. A
     * staff token pointed at these endpoints fails the first check, and an
     * account that belongs to hospital A asking about hospital B fails the
     * second.
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function patient(): Patient
    {
        if ($this->patient) {
            return $this->patient;
        }

        $tenant = $this->tenant();
        $user = $this->user();

        $isMember = TenantUser::on('landlord')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMember || !$this->holdsPatientRole($user, $tenant)) {
            throw new PatientAppException('You are not registered with this hospital.', 403);
        }

        $patient = Patient::query()
            ->where('tenant_id', $tenant->uuid)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('email', $user->email);
            })
            ->orderBy('id', 'DESC')
            ->first();

        if (!$patient) {
            throw new PatientAppException('We could not find your record at this hospital.', 404);
        }

        return $this->patient = $patient;
    }

    /**
     * The tenant uuid appointments and departments are stamped with.
     */
    public function tenantUuid(): string
    {
        return $this->tenant()->uuid;
    }

    /**
     * Whether the account is a patient of this hospital rather than a member of
     * its staff.
     */
    protected function holdsPatientRole(User $user, Tenant $tenant): bool
    {
        return $user->roles()
            ->where('roles.tenant_id', $tenant->uuid)
            ->where('roles.name', RoleEnums::PATIENT->value)
            ->exists();
    }
}
