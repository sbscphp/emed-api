<?php

namespace App\Services\Patient\Appointment;

use App\Enums\RoleEnums;
use App\Exceptions\PatientAppException;
use App\Models\Appointment;
use App\Models\Department;
use App\Models\User;
use App\Services\Patient\PatientContextService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Class AppointmentSlotService
 *
 * Everything the booking flow needs before an appointment exists: the
 * departments to choose from, the doctors that practise in the one chosen, the
 * days that doctor can be seen on, and the times still free on a given day.
 *
 * Hospitals do not publish per doctor rotas yet, so a doctor's diary is derived
 * rather than read: the clinic hours in config/patient_app.php are sliced into
 * slots, and every slot the doctor is already booked for is struck out. The day
 * a rota table arrives, only the generator methods below have to change.
 */
class AppointmentSlotService
{
    public function __construct(protected PatientContextService $context) {}

    /**
     * The departments a patient can book into, for the "Select Department"
     * screen. Only the active ones: a department the hospital has switched off
     * is no longer taking bookings.
     *
     * @param  string|null  $search
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Department>
     */
    public function departments(?string $search = null)
    {
        return Department::query()
            ->forTenant($this->context->tenantUuid())
            ->where('status', true)
            ->when(!empty($search), function ($query) use ($search) {
                $query->where('name', 'LIKE', '%' . $search . '%');
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * The doctors of one department, for the "Select your preferred doctor"
     * screen.
     *
     * Doctors are the hospital's consultants, and a department publishes its own
     * through the department_user table. A department nobody has assigned anyone
     * to yet falls back to every consultant of the hospital: an empty list would
     * strand the patient mid booking, and a hospital that has not filled the
     * assignments in would otherwise take no bookings at all.
     *
     * @param  int  $departmentId
     * @param  string|null  $search
     * @return \Illuminate\Support\Collection<int, \App\Models\User>
     */
    public function doctors($departmentId, ?string $search = null): Collection
    {
        $department = $this->department($departmentId);
        $consultantIds = $this->consultantIds();

        if (empty($consultantIds)) {
            return collect();
        }

        $assigned = array_values(array_intersect($department->doctorIds(), $consultantIds));
        $doctorIds = !empty($assigned) ? $assigned : $consultantIds;

        return User::on('landlord')
            ->whereIn('id', $doctorIds)
            ->when(!empty($search), function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('fullname', 'LIKE', '%' . $search . '%')
                        ->orWhere('first_name', 'LIKE', '%' . $search . '%')
                        ->orWhere('last_name', 'LIKE', '%' . $search . '%');
                });
            })
            ->orderBy('fullname')
            ->get()
            ->each(function (User $doctor) use ($department) {
                // Carried along so the picker can print the department under
                // each name without a second lookup.
                $doctor->setAttribute('department_name', $department->name);
            });
    }

    /**
     * The days the booking calendar may paint, each marked bookable or not.
     *
     * @param  int  $doctorId
     * @param  string|null  $from
     * @param  string|null  $to
     * @return array{from:string, to:string, days:array<int, array>}
     */
    public function availability($doctorId, $from = null, $to = null): array
    {
        $doctor = $this->doctor($doctorId);
        $horizon = (int) config('patient_app.appointments.booking_horizon_days');

        // Clamped to today and to the horizon, whatever month the calendar was
        // scrolled to.
        $start = $from ? Carbon::parse($from)->startOfDay() : Carbon::today();
        $end = $to ? Carbon::parse($to)->endOfDay() : (clone $start)->addDays(30)->endOfDay();

        $start = $start->max(Carbon::today());
        $end = $end->min(Carbon::today()->addDays($horizon)->endOfDay());

        if ($end->lt($start)) {
            return ['from' => $start->toDateString(), 'to' => $start->toDateString(), 'days' => []];
        }

        // One query for the whole range rather than one per day.
        $booked = $this->bookedTimes($doctor->id, $start->toDateString(), $end->toDateString());

        $days = [];

        foreach (CarbonPeriod::create($start, $end) as $day) {
            $date = $day->toDateString();
            $slots = $this->generateSlots($day, $booked->get($date, collect()));
            $available = collect($slots)->where('available', true)->count();

            $days[] = [
                'date' => $date,
                'day_name' => $day->format('l'),
                'is_working_day' => $this->isWorkingDay($day),
                'available_slots' => $available,
                'is_available' => $available > 0,
            ];
        }

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'days' => $days,
        ];
    }

    /**
     * The time slots of one day, grouped the way the picker renders them.
     *
     * Booked and past slots come back too, flagged unavailable rather than left
     * out: the screen greys them in place so the day keeps its shape.
     *
     * @param  int  $doctorId
     * @param  string  $date
     * @param  int|null  $ignoreAppointmentId  the appointment being rescheduled
     * @return array{date:string, is_working_day:bool, available_slots:int, sessions:array<int, array>}
     */
    public function slots($doctorId, $date, $ignoreAppointmentId = null): array
    {
        $doctor = $this->doctor($doctorId);
        $day = Carbon::parse($date)->startOfDay();

        $booked = $this->bookedTimes($doctor->id, $day->toDateString(), $day->toDateString(), $ignoreAppointmentId)
            ->get($day->toDateString(), collect());

        $slots = collect($this->generateSlots($day, $booked));

        $sessions = [];

        foreach (array_keys((array) config('patient_app.appointments.sessions')) as $label) {
            $sessions[] = [
                'name' => $label,
                'slots' => $slots->where('session', $label)->values()->all(),
            ];
        }

        return [
            'date' => $day->toDateString(),
            'is_working_day' => $this->isWorkingDay($day),
            'available_slots' => $slots->where('available', true)->count(),
            'sessions' => $sessions,
        ];
    }

    /**
     * The video platforms a tele consultation can be held on.
     *
     * @return array<int, array{name:string, slug:string}>
     */
    public function meetingPlatforms(): array
    {
        return array_values((array) config('patient_app.appointments.meeting_platforms', []));
    }

    /**
     * The names of those platforms, which is what the appointment stores.
     *
     * @return array<int, string>
     */
    public function meetingPlatformNames(): array
    {
        return array_column($this->meetingPlatforms(), 'name');
    }

    /**
     * Refuse a date and time the doctor cannot actually be seen at.
     *
     * Checked at the moment of booking rather than only while the patient
     * browses: two patients can be looking at the same free slot at the same
     * time, and the one who taps "Confirm Appointment" second has to be told.
     *
     * @param  int  $doctorId
     * @param  string  $date
     * @param  string  $time  normalised to H:i:s
     * @param  int|null  $ignoreAppointmentId
     * @return void
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function assertSlotIsBookable($doctorId, $date, $time, $ignoreAppointmentId = null): void
    {
        $day = Carbon::parse($date)->startOfDay();
        $moment = Carbon::parse($day->toDateString() . ' ' . $time);

        if ($day->gt(Carbon::today()->addDays((int) config('patient_app.appointments.booking_horizon_days')))) {
            throw new PatientAppException('That date is too far ahead to book yet. Please choose an earlier date.', 422);
        }

        if (!$this->isWorkingDay($day)) {
            throw new PatientAppException('The hospital is closed on that day. Please choose another date.', 422);
        }

        if ($moment->lt($this->earliestBookableMoment())) {
            throw new PatientAppException('That time has already passed. Please choose a later slot.', 422);
        }

        if (!$this->fallsInAClinicSession($moment)) {
            throw new PatientAppException('That time is outside the consulting hours of the hospital.', 422);
        }

        $taken = $this->bookedTimes($doctorId, $day->toDateString(), $day->toDateString(), $ignoreAppointmentId)
            ->get($day->toDateString(), collect())
            ->contains($moment->format('H:i'));

        if ($taken) {
            throw new PatientAppException('That slot has just been taken. Please choose another time.', 409);
        }
    }

    /**
     * Resolve a department of the current hospital.
     *
     * @param  int  $departmentId
     * @return \App\Models\Department
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function department($departmentId): Department
    {
        $department = Department::query()
            ->forTenant($this->context->tenantUuid())
            ->find($departmentId);

        if (!$department) {
            throw new PatientAppException('The selected department is not available at this hospital.', 404);
        }

        return $department;
    }

    /**
     * Resolve a doctor of the current hospital.
     *
     * @param  int  $doctorId
     * @return \App\Models\User
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function doctor($doctorId): User
    {
        $consultantIds = $this->consultantIds();

        $doctor = empty($consultantIds)
            ? null
            : User::on('landlord')->whereIn('id', $consultantIds)->find($doctorId);

        if (!$doctor) {
            throw new PatientAppException('The selected doctor is not available at this hospital.', 404);
        }

        return $doctor;
    }

    /**
     * Check that a doctor really belongs to the department being booked, so an
     * appointment cannot record a pairing the app never offered.
     *
     * Skipped where the department has no assignments of its own: the doctor
     * list fell back to every consultant, so every consultant is a valid answer.
     *
     * @param  int  $departmentId
     * @param  int  $doctorId
     * @return void
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function assertDoctorBelongsToDepartment($departmentId, $doctorId): void
    {
        $assigned = $this->department($departmentId)->doctorIds();

        if (!empty($assigned) && !in_array((int) $doctorId, $assigned, true)) {
            throw new PatientAppException('The selected doctor does not consult in that department.', 422);
        }
    }

    /**
     * Slice one day into slots and mark each bookable or not.
     *
     * @param  \Carbon\Carbon  $day
     * @param  \Illuminate\Support\Collection<int, string>  $booked  times as H:i
     * @return array<int, array>
     */
    protected function generateSlots(Carbon $day, Collection $booked): array
    {
        if (!$this->isWorkingDay($day)) {
            return [];
        }

        $slotMinutes = max(5, (int) config('patient_app.appointments.slot_minutes'));
        $earliest = $this->earliestBookableMoment();
        $slots = [];

        foreach ((array) config('patient_app.appointments.sessions') as $label => $window) {
            $cursor = Carbon::parse($day->toDateString() . ' ' . $window['start']);
            $closes = Carbon::parse($day->toDateString() . ' ' . $window['end']);

            while ($cursor->lt($closes)) {
                $time = $cursor->format('H:i');
                $isTaken = $booked->contains($time);
                $hasPassed = $cursor->lt($earliest);

                $slots[] = [
                    'session' => $label,
                    'time' => $time,
                    'label' => $cursor->format('h:i A'),
                    'available' => !$isTaken && !$hasPassed,
                    'reason' => $isTaken ? 'Already booked' : ($hasPassed ? 'Too late to book' : null),
                ];

                $cursor->addMinutes($slotMinutes);
            }
        }

        return $slots;
    }

    /**
     * The times a doctor is already spoken for, keyed by date.
     *
     * Cancelled appointments and no shows release their slot; everything else
     * holds on to it.
     *
     * @param  int  $doctorId
     * @param  string  $from
     * @param  string  $to
     * @param  int|null  $ignoreAppointmentId
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, string>>
     */
    protected function bookedTimes($doctorId, $from, $to, $ignoreAppointmentId = null): Collection
    {
        return Appointment::query()
            ->forTenant($this->context->tenantUuid())
            ->where('doctor_id', $doctorId)
            ->whereBetween('date', [$from, $to])
            ->whereNotIn('status', ['Canceled', 'No show'])
            ->when($ignoreAppointmentId, function ($query) use ($ignoreAppointmentId) {
                $query->where('id', '!=', $ignoreAppointmentId);
            })
            ->get(['id', 'date', 'time'])
            ->groupBy(fn($appointment) => $appointment->date->format('Y-m-d'))
            ->map(fn($group) => $group
                ->map(fn($appointment) => Carbon::parse($appointment->time)->format('H:i'))
                ->unique()
                ->values());
    }

    /**
     * The earliest moment a patient may book into: now, plus the notice the
     * hospital asks for.
     */
    protected function earliestBookableMoment(): Carbon
    {
        return Carbon::now()->addHours((int) config('patient_app.appointments.minimum_notice_hours'));
    }

    /**
     * Whether the clinic runs on this day of the week.
     */
    protected function isWorkingDay(Carbon $day): bool
    {
        $workingDays = array_map('intval', (array) config('patient_app.appointments.working_days', []));

        return in_array($day->dayOfWeekIso, $workingDays, true);
    }

    /**
     * Whether a moment lands inside one of the consulting windows.
     */
    protected function fallsInAClinicSession(Carbon $moment): bool
    {
        foreach ((array) config('patient_app.appointments.sessions') as $window) {
            $opens = Carbon::parse($moment->toDateString() . ' ' . $window['start']);
            $closes = Carbon::parse($moment->toDateString() . ' ' . $window['end']);

            if ($moment->gte($opens) && $moment->lt($closes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The landlord user ids of every consultant at the current hospital.
     *
     * Roles and their pivot live in the tenant database while users live on the
     * landlord one, so the ids are read on one connection and spent on the
     * other rather than joined across both.
     *
     * @return array<int, int>
     */
    protected function consultantIds(): array
    {
        $tenant = $this->context->tenant();

        $roleUserIds = DB::connection('tenant')
            ->table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.tenant_id', $tenant->uuid)
            ->where('roles.name', RoleEnums::CONSULTANT->value)
            ->pluck('role_user.user_id')
            ->map(fn($id) => (int) $id)
            ->all();

        if (empty($roleUserIds)) {
            return [];
        }

        // A consultant who has since left the hospital keeps the role row but
        // loses the membership, so the membership is what is trusted.
        return DB::connection('landlord')
            ->table('tenant_users')
            ->where('tenant_id', $tenant->id)
            ->whereIn('user_id', $roleUserIds)
            ->whereNull('deleted_at')
            ->pluck('user_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
