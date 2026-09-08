<?php

namespace App\Services\Patient\Hospital;

use App\Enums\RoleEnums;
use App\Exceptions\PatientAppException;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\Patient\PatientContextService;
use Illuminate\Support\Collection;

/**
 * Class PatientHospitalService
 *
 * The Linked Hospitals screen: every hospital that has registered this account,
 * and one of them opened up.
 *
 * This is the one patient module that is not about a single hospital, so it runs
 * without the tenant header. It reads the landlord's membership table rather
 * than any hospital's own database, and only steps into a hospital's database
 * when it needs the patient number that hospital knows them by.
 *
 * Which hospital is "primary" is derived rather than stored: it is the one that
 * registered the account first, which is the hospital whose invitation created
 * it and the one a patient thinks of as theirs. Deriving it means there is no
 * flag to fall out of step, and no migration for a fact the join dates already
 * carry.
 */
class PatientHospitalService
{
    public function __construct(protected PatientContextService $context) {}

    /**
     * Every hospital linked to the signed in account, oldest link first.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Tenant>
     */
    public function index(): Collection
    {
        $user = $this->context->user();

        $memberships = TenantUser::on('landlord')
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($memberships->isEmpty()) {
            return collect();
        }

        $tenants = Tenant::whereIn('id', $memberships->pluck('tenant_id'))->get()->keyBy('id');

        // The first membership that resolves to a hospital is the primary one.
        // Taken from the memberships rather than the tenants so a hospital that
        // has since been deleted does not hand the title to the wrong one.
        $primaryId = $memberships
            ->first(fn($membership) => $tenants->has($membership->tenant_id))?->tenant_id;

        return $memberships
            ->map(function ($membership) use ($tenants, $primaryId, $user) {
                $tenant = $tenants->get($membership->tenant_id);

                if (!$tenant) {
                    return null;
                }

                return $this->decorate($tenant, $membership, $tenant->id === $primaryId, $user->id);
            })
            ->filter()
            ->values();
    }

    /**
     * One hospital's card, by its uuid.
     *
     * Reads the list rather than fetching the tenant directly, so a hospital the
     * account is not linked to is a 404 here and cannot be probed for its
     * address and phone number.
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function show(string $uuid): Tenant
    {
        $hospital = $this->index()->first(fn($tenant) => $tenant->uuid === $uuid);

        if (!$hospital) {
            throw new PatientAppException('You are not registered with this hospital.', 404);
        }

        return $hospital;
    }

    /**
     * Attach what the screen shows that the tenants row does not hold.
     */
    protected function decorate(Tenant $tenant, TenantUser $membership, bool $isPrimary, int $userId): Tenant
    {
        $tenant->setAttribute('is_primary', $isPrimary);
        $tenant->setAttribute('relationship', $isPrimary ? 'Primary Hospital' : 'Linked');
        $tenant->setAttribute('linked_at', optional($membership->created_at)->toDateTimeString());
        $tenant->setAttribute('is_active', (bool) $membership->is_active);
        $tenant->setAttribute('patient_number', $this->patientNumberAt($tenant, $userId));

        return $tenant;
    }

    /**
     * The number this hospital files the patient under.
     *
     * Each hospital keeps its own record, so this means stepping into that
     * hospital's database. A hospital that has linked the account but not yet
     * created a record simply has no number to show, which is not an error.
     */
    protected function patientNumberAt(Tenant $tenant, int $userId): ?string
    {
        try {
            $tenant->makeCurrent();

            $patient = Patient::query()
                ->where('tenant_id', $tenant->uuid)
                ->where(function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                        ->orWhere('email', $this->context->user()->email);
                })
                ->orderBy('id', 'DESC')
                ->first();

            return $patient?->patientno;
        } catch (\Throwable $th) {
            return null;
        }
    }

    /**
     * Whether the account still holds the patient role at a hospital.
     *
     * Not used by the list — a hospital the patient was registered by belongs on
     * it either way — but kept alongside it for callers that need to know
     * whether the app may still read records there.
     */
    public function holdsPatientRole(Tenant $tenant): bool
    {
        return $this->context->user()->roles()
            ->where('roles.tenant_id', $tenant->uuid)
            ->where('roles.name', RoleEnums::PATIENT->value)
            ->exists();
    }
}
