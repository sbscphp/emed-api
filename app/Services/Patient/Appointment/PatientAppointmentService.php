<?php

namespace App\Services\Patient\Appointment;

use App\Enums\AppointmentStatusEnums;
use App\Enums\ListModuleEnums;
use App\Exceptions\PatientAppException;
use App\Helpers\GeneralHelper;
use App\Models\Appointment;
use App\Models\Notification;
use App\Services\Patient\Concerns\ResolvesDateFilters;
use App\Services\Patient\PatientContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class PatientAppointmentService
 *
 * The appointment module of the patient mobile app: the three tabbed lists the
 * patient browses, the single appointment they open from them, the booking flow
 * that creates one, and the two things they can do to one afterwards, which are
 * to move or call it off, and to say they have arrived.
 *
 * Everything here is scoped twice over, by the hospital in the X-Tenant-ID
 * header and by the patient record the signed in account holds there, so an
 * appointment id guessed from another patient's is simply not found.
 *
 * The admin schedule keeps its own service; the two are deliberately separate.
 * A receptionist may book into any slot, move an appointment an hour before it
 * starts and mark it completed, while a patient may only take a free slot, has
 * to give notice, and never touches an outcome.
 */
class PatientAppointmentService
{
    use ResolvesDateFilters;

    /**
     * The relations every appointment payload is built from.
     *
     * @var array<int, string>
     */
    protected array $relations = ['department', 'doctor'];

    /**
     * The tabs the appointment list is split into.
     *
     * @var array<int, string>
     */
    public const TABS = ['upcoming', 'past', 'cancelled'];

    public function __construct(
        protected PatientContextService $context,
        protected AppointmentSlotService $slots,
    ) {}

    /**
     * The patient's appointments, filtered by the tab, status and dates the
     * list screen offers.
     *
     * Dates come in either as one day from the calendar, or as a range — a
     * `period` the app names, or a From and To pair — which is resolved by
     * GeneralHelper::dateFilter the way every other module in the codebase
     * resolves one.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
     */
    public function index($request)
    {
        $dateFilter = $this->dateFilter($request);

        $records = $this->baseQuery()
            ->when(!empty($request['tab']), function ($query) use ($request) {
                $this->applyTab($query, $request['tab']);
            })
            ->when(!empty($request['status']), function ($query) use ($request) {
                $query->where('status', $request['status']);
            })
            ->when(!empty($request['visit_type']), function ($query) use ($request) {
                $query->where('visit_type', $request['visit_type']);
            })
            ->when(!empty($request['department_id']), function ($query) use ($request) {
                $query->where('department_id', $request['department_id']);
            })
            ->when(!empty($request['date']), function ($query) use ($request) {
                $query->whereDate('date', Carbon::parse($request['date'])->toDateString());
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('date', $dateFilter);
            })
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $search = $request['search_param'];
                $query->where(function ($q) use ($search) {
                    $q->where('appointment_no', 'LIKE', '%' . $search . '%')
                        ->orWhere('reason', 'LIKE', '%' . $search . '%')
                        ->orWhereRelation('department', 'name', 'LIKE', '%' . $search . '%');
                });
            })
            ->with($this->relations);

        // Upcoming reads forwards, because the next appointment is the one the
        // patient came to see; every other tab reads backwards from the most
        // recent.
        $direction = ($request['tab'] ?? null) === 'upcoming' ? 'ASC' : 'DESC';

        $records->orderBy('date', $direction)->orderBy('time', $direction);

        if (!empty($request['paginate'])) {
            return $records->paginate($request['limit'] ?? 15);
        }

        return $records->get();
    }

    /**
     * How many appointments sit behind each tab, for their badges.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'upcoming' => $this->applyTab($this->baseQuery(), 'upcoming')->count(),
            'past' => $this->applyTab($this->baseQuery(), 'past')->count(),
            'cancelled' => $this->applyTab($this->baseQuery(), 'cancelled')->count(),
            'total' => $this->baseQuery()->count(),
        ];
    }

    /**
     * The appointment the home screen puts at the bottom of the dashboard.
     *
     * The next one still to come, falling back to the one most recently held so
     * the card is not empty for a patient between visits.
     *
     * @return \App\Models\Appointment|null
     */
    public function latest()
    {
        $next = $this->applyTab($this->baseQuery(), 'upcoming')
            ->with($this->relations)
            ->orderBy('date', 'ASC')
            ->orderBy('time', 'ASC')
            ->first();

        if ($next) {
            return $next;
        }

        return $this->baseQuery()
            ->with($this->relations)
            ->orderBy('date', 'DESC')
            ->orderBy('time', 'DESC')
            ->first();
    }

    /**
     * One appointment of the signed in patient.
     *
     * @param  int  $id
     * @return \App\Models\Appointment
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function show($id): Appointment
    {
        $record = $this->baseQuery()->with($this->relations)->find($id);

        if (!$record) {
            throw new PatientAppException('We could not find that appointment.', 404);
        }

        return $record;
    }

    /**
     * An appointment dressed for the detail screen.
     *
     * The three things that screen shows and the row itself cannot answer — the
     * hospital it is held at, the state of the check in, and which of the two
     * buttons at the bottom are live — are worked out here rather than in the
     * resource, because all three are questions about time, configuration and
     * the tenant rather than about presentation.
     *
     * @param  \App\Models\Appointment  $record
     * @return \App\Models\Appointment
     */
    public function decorate(Appointment $record): Appointment
    {
        $tenant = $this->context->tenant();

        $record->setAttribute('hospital', [
            'uuid' => $tenant->uuid,
            'name' => $tenant->name,
            'logo' => $tenant->logo,
            'address' => $tenant->address,
        ]);

        $record->setAttribute('check_in', $this->checkInStatus($record));
        $record->setAttribute('actions', $this->actions($record));

        return $record;
    }

    /**
     * Which of the buttons on the detail screen are live, and why not when they
     * are not.
     *
     * @return array<string, mixed>
     */
    public function actions(Appointment $record): array
    {
        $canReschedule = true;
        $rescheduleMessage = null;

        try {
            $this->assertIsEditable($record);
        } catch (PatientAppException $th) {
            $canReschedule = false;
            $rescheduleMessage = $th->getMessage();
        }

        $canCancel = in_array($record->status, [
            AppointmentStatusEnums::SCHEDULED->value,
            AppointmentStatusEnums::CHECKED_IN->value,
        ], true);

        $cancelMessage = null;
        $notice = (int) config('patient_app.appointments.cancellation_notice_hours');

        if ($canCancel && $record->starts_at && $record->starts_at->lt(Carbon::now()->addHours($notice))) {
            $canCancel = false;
            $cancelMessage = 'It is now too close to the appointment to cancel from the app. Please call the hospital.';
        }

        return [
            'can_reschedule' => $canReschedule,
            'reschedule_message' => $rescheduleMessage,
            'can_cancel' => $canCancel,
            'cancel_message' => $cancelMessage,
            // A cancelled appointment is the only one a patient may clear out of
            // their own list.
            'can_remove' => $record->status === AppointmentStatusEnums::CANCELED->value,
        ];
    }

    /**
     * Book an appointment from the patient app.
     *
     * @param  array<string, mixed>  $data
     * @return \App\Models\Appointment
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function book(array $data): Appointment
    {
        $patient = $this->context->patient();
        $tenant = $this->context->tenant();

        $visitType = $this->visitTypeFor($data['consultation_type']);
        $date = Carbon::parse($data['date'])->toDateString();
        $time = $this->normalizeTime($data['time']);

        $this->slots->assertDoctorBelongsToDepartment($data['department_id'], $data['doctor_id']);
        $this->slots->assertSlotIsBookable($data['doctor_id'], $date, $time);
        $this->assertPatientIsFree($date, $time);

        $record = DB::connection('tenant')->transaction(function () use ($data, $patient, $tenant, $visitType, $date, $time) {
            return Appointment::create([
                'tenant_uuid' => $tenant->uuid,
                'appointment_no' => $this->generateAppointmentNo(),
                'patient_id' => $patient->id,
                'appointment_type' => $data['appointment_type'] ?? 'Consultation',
                'department_id' => $data['department_id'],
                'doctor_id' => $data['doctor_id'],
                'visit_type' => $visitType,
                'date' => $date,
                'time' => $time,
                'duration' => $this->defaultDuration(),
                'reason' => $data['reason'] ?? null,
                'status' => AppointmentStatusEnums::SCHEDULED->value,
                'booking_source' => 'Patient',
                'booked_by' => $this->context->user()->id,
                // The admin schedule reads created_by for "who put this in the
                // diary", so it is filled in too rather than left to look like
                // a row nobody created.
                'created_by' => $this->context->user()->id,
                // Only a video consultation carries a platform, whatever the app
                // happened to send.
                'meeting_platform' => $this->isVirtual($visitType) ? ($data['meeting_platform'] ?? null) : null,
            ]);
        });

        $record->load($this->relations);

        $this->notifyHospital(
            $record,
            'New Appointment Booked',
            sprintf(
                '%s booked a %s with %s in %s on %s.',
                $this->patientName($record),
                strtolower($record->visit_type),
                $this->doctorName($record) ?: 'a doctor',
                optional($record->department)->name ?: 'the hospital',
                $this->readableMoment($record)
            )
        );

        $this->log($record, 'Create', 'Appointment booked from the patient app', sprintf(
            '%s booked the appointment (%s) from the patient app',
            $this->patientName($record),
            $record->appointment_no
        ), [], $record->toArray());

        return $record;
    }

    /**
     * Move an appointment, or correct what the patient wrote on it.
     *
     * Everything is optional: the same endpoint serves the reschedule sheet, the
     * "edit reason" field and the meeting platform switcher.
     *
     * @param  int  $id
     * @param  array<string, mixed>  $data
     * @return \App\Models\Appointment
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function update($id, array $data): Appointment
    {
        $record = $this->show($id);
        $oldData = $record->toArray();

        $this->assertIsEditable($record);

        $changes = [];

        if (array_key_exists('consultation_type', $data) && !empty($data['consultation_type'])) {
            $changes['visit_type'] = $this->visitTypeFor($data['consultation_type']);
        }

        if (array_key_exists('reason', $data)) {
            $changes['reason'] = $data['reason'];
        }

        $visitType = $changes['visit_type'] ?? $record->visit_type;

        if (array_key_exists('meeting_platform', $data) || array_key_exists('visit_type', $changes)) {
            $changes['meeting_platform'] = $this->isVirtual($visitType)
                ? ($data['meeting_platform'] ?? $record->meeting_platform)
                : null;
        }

        $doctorId = $data['doctor_id'] ?? $record->doctor_id;
        $departmentId = $data['department_id'] ?? $record->department_id;

        if (array_key_exists('department_id', $data) || array_key_exists('doctor_id', $data)) {
            $this->slots->assertDoctorBelongsToDepartment($departmentId, $doctorId);

            $changes['department_id'] = $departmentId;
            $changes['doctor_id'] = $doctorId;
        }

        $isMoving = !empty($data['date']) || !empty($data['time']) || (int) $doctorId !== (int) $record->doctor_id;

        if ($isMoving) {
            $date = !empty($data['date'])
                ? Carbon::parse($data['date'])->toDateString()
                : $record->date->format('Y-m-d');
            $time = !empty($data['time'])
                ? $this->normalizeTime($data['time'])
                : $this->normalizeTime($record->time);

            $this->slots->assertSlotIsBookable($doctorId, $date, $time, $record->id);
            $this->assertPatientIsFree($date, $time, $record->id);

            $changes['date'] = $date;
            $changes['time'] = $time;
        }

        if (empty($changes)) {
            return $record;
        }

        DB::connection('tenant')->transaction(function () use ($record, $changes) {
            $record->update($changes);
        });

        $record->refresh()->load($this->relations);

        if ($isMoving) {
            $this->notifyHospital(
                $record,
                'Appointment Rescheduled',
                sprintf(
                    '%s moved the appointment %s to %s.',
                    $this->patientName($record),
                    $record->appointment_no,
                    $this->readableMoment($record)
                )
            );
        }

        $this->log($record, 'Update', 'Appointment updated from the patient app', sprintf(
            '%s updated the appointment (%s) from the patient app',
            $this->patientName($record),
            $record->appointment_no
        ), $oldData, $record->toArray());

        return $record;
    }

    /**
     * Call an appointment off.
     *
     * The row stays: cancelled appointments are a tab of their own in the app,
     * and the hospital wants the history.
     *
     * @param  int  $id
     * @param  string|null  $reason
     * @return \App\Models\Appointment
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function cancel($id, ?string $reason = null): Appointment
    {
        $record = $this->show($id);
        $oldData = $record->toArray();

        if ($record->status === AppointmentStatusEnums::CANCELED->value) {
            throw new PatientAppException('This appointment has already been cancelled.', 409);
        }

        if (in_array($record->status, [
            AppointmentStatusEnums::COMPLETED->value,
            AppointmentStatusEnums::NO_SHOW->value,
        ], true)) {
            throw new PatientAppException('This appointment has already been held and can no longer be cancelled.', 409);
        }

        $notice = (int) config('patient_app.appointments.cancellation_notice_hours');

        if ($record->starts_at && $record->starts_at->lt(Carbon::now()->addHours($notice))) {
            throw new PatientAppException(sprintf(
                'An appointment can only be cancelled from the app up to %d hour%s before it starts. Please call the hospital.',
                $notice,
                $notice === 1 ? '' : 's'
            ), 422);
        }

        DB::connection('tenant')->transaction(function () use ($record, $reason) {
            $record->update([
                'status' => AppointmentStatusEnums::CANCELED->value,
                'cancelled_at' => now(),
                'cancelled_by' => $this->context->user()->id,
                'cancellation_reason' => $reason,
            ]);
        });

        $record->refresh()->load($this->relations);

        $this->notifyHospital(
            $record,
            'Appointment Cancelled',
            sprintf(
                '%s cancelled the appointment %s scheduled for %s.%s',
                $this->patientName($record),
                $record->appointment_no,
                $this->readableMoment($record),
                $reason ? ' Reason: ' . $reason : ''
            )
        );

        $this->log($record, 'Update', 'Appointment cancelled from the patient app', sprintf(
            '%s cancelled the appointment (%s) from the patient app',
            $this->patientName($record),
            $record->appointment_no
        ), $oldData, $record->toArray());

        return $record;
    }

    /**
     * "Confirm Check in" — the patient telling the hospital they have arrived.
     *
     * @param  int  $id
     * @return \App\Models\Appointment
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function checkIn($id): Appointment
    {
        $record = $this->show($id);
        $oldData = $record->toArray();

        if ($record->is_virtual) {
            throw new PatientAppException('A video consultation does not need a check in. Join from the appointment when it is time.', 422);
        }

        if ($record->status === AppointmentStatusEnums::CHECKED_IN->value) {
            throw new PatientAppException('You have already checked in for this appointment.', 409);
        }

        if ($record->status !== AppointmentStatusEnums::SCHEDULED->value) {
            throw new PatientAppException('This appointment is no longer open for check in.', 409);
        }

        $this->assertCheckInWindowIsOpen($record);

        DB::connection('tenant')->transaction(function () use ($record) {
            $record->update([
                'status' => AppointmentStatusEnums::CHECKED_IN->value,
                'checked_in_at' => now(),
            ]);
        });

        $record->refresh()->load($this->relations);

        $this->notifyHospital(
            $record,
            'Patient Checked In',
            sprintf(
                '%s has arrived for the %s appointment (%s) with %s.',
                $this->patientName($record),
                $this->readableMoment($record),
                $record->appointment_no,
                $this->doctorName($record) ?: 'the doctor'
            )
        );

        $this->log($record, 'Update', 'Patient checked in from the patient app', sprintf(
            '%s checked in for the appointment (%s)',
            $this->patientName($record),
            $record->appointment_no
        ), $oldData, $record->toArray());

        return $record;
    }

    /**
     * Clear a cancelled appointment out of the patient's list.
     *
     * Soft delete only, and only once the appointment is cancelled: the hospital
     * keeps the record either way, and an appointment still in the diary has to
     * be cancelled first so the slot and the notification are not skipped.
     *
     * @param  int  $id
     * @return \App\Models\Appointment
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function destroy($id): Appointment
    {
        $record = $this->show($id);

        if ($record->status !== AppointmentStatusEnums::CANCELED->value) {
            throw new PatientAppException('Please cancel this appointment before removing it.', 422);
        }

        $oldData = $record->toArray();

        $record->delete();

        $this->log($record, 'Delete', 'Appointment removed from the patient app', sprintf(
            '%s removed the cancelled appointment (%s) from their list',
            $this->patientName($record),
            $record->appointment_no
        ), $oldData, []);

        return $record;
    }

    /**
     * The state of the check in screen for one appointment: whether the button
     * is live yet, and once it has been pressed, the queue behind it.
     *
     * @param  \App\Models\Appointment  $record
     * @return array<string, mixed>
     */
    public function checkInStatus(Appointment $record): array
    {
        $isCheckedIn = $record->status === AppointmentStatusEnums::CHECKED_IN->value;

        $canCheckIn = false;
        $message = null;

        if (!$isCheckedIn && !$record->is_virtual && $record->status === AppointmentStatusEnums::SCHEDULED->value) {
            try {
                $this->assertCheckInWindowIsOpen($record);
                $canCheckIn = true;
            } catch (PatientAppException $th) {
                $message = $th->getMessage();
            }
        }

        return [
            'is_checked_in' => $isCheckedIn,
            'can_check_in' => $canCheckIn,
            'message' => $message,
            'checked_in_at' => optional($record->checked_in_at)->format('h:i A'),
            'current_status' => $isCheckedIn ? 'Awaiting service' : null,
            'estimated_wait_minutes' => $isCheckedIn ? $this->estimatedWaitMinutes($record) : null,
        ];
    }

    /**
     * The two cards the booking flow opens on.
     *
     * @return array<int, array<string, string>>
     */
    public function consultationTypes(): array
    {
        return [
            [
                'key' => 'in_person',
                'name' => 'In person Consultation',
                'description' => 'Visit the hospital and meet your doctor in person',
                'visit_type' => Appointment::CONSULTATION_TYPES['in_person'],
            ],
            [
                'key' => 'tele',
                'name' => 'Tele consultation',
                'description' => 'Speak with your doctor through a secure video call',
                'visit_type' => Appointment::CONSULTATION_TYPES['tele'],
            ],
        ];
    }

    /**
     * Every appointment query starts here: this hospital, this patient.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        return Appointment::query()
            ->forTenant($this->context->tenantUuid())
            ->forPatient($this->context->patient()->id);
    }

    /**
     * Narrow a query to one of the list screen's three tabs.
     *
     * Upcoming and past are told apart by the end of the appointment's day
     * rather than by its exact time, so an appointment does not drop out of
     * "Upcoming" while the patient is still sitting in the waiting room.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $tab
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyTab($query, string $tab)
    {
        $cancelled = AppointmentStatusEnums::CANCELED->value;
        $today = Carbon::today()->toDateString();

        return match (strtolower($tab)) {
            'cancelled', 'canceled' => $query->where('status', $cancelled),

            'past' => $query->where('status', '!=', $cancelled)
                ->where(function ($q) use ($today) {
                    $q->whereDate('date', '<', $today)
                        ->orWhereIn('status', [
                            AppointmentStatusEnums::COMPLETED->value,
                            AppointmentStatusEnums::NO_SHOW->value,
                        ]);
                }),

            'upcoming' => $query->whereNotIn('status', [
                $cancelled,
                AppointmentStatusEnums::COMPLETED->value,
                AppointmentStatusEnums::NO_SHOW->value,
            ])->whereDate('date', '>=', $today),

            default => $query,
        };
    }

    /**
     * Refuse a change to an appointment that is no longer the patient's to make.
     *
     * @throws \App\Exceptions\PatientAppException
     */
    protected function assertIsEditable(Appointment $record): void
    {
        if (in_array($record->status, [
            AppointmentStatusEnums::CANCELED->value,
            AppointmentStatusEnums::COMPLETED->value,
            AppointmentStatusEnums::NO_SHOW->value,
        ], true)) {
            throw new PatientAppException('This appointment can no longer be changed.', 422);
        }

        if ($record->status === AppointmentStatusEnums::CHECKED_IN->value) {
            throw new PatientAppException('You have already checked in for this appointment, so it can no longer be changed.', 422);
        }

        $notice = (int) config('patient_app.appointments.reschedule_notice_hours');

        if ($record->starts_at && $record->starts_at->lt(Carbon::now()->addHours($notice))) {
            throw new PatientAppException(sprintf(
                'An appointment can only be changed from the app up to %d hour%s before it starts. Please call the hospital.',
                $notice,
                $notice === 1 ? '' : 's'
            ), 422);
        }
    }

    /**
     * Refuse a slot the patient has already booked something else into, whoever
     * the other appointment is with.
     *
     * @throws \App\Exceptions\PatientAppException
     */
    protected function assertPatientIsFree($date, $time, $ignoreAppointmentId = null): void
    {
        $clash = $this->baseQuery()
            ->whereDate('date', $date)
            ->where('time', $time)
            ->whereNotIn('status', [
                AppointmentStatusEnums::CANCELED->value,
                AppointmentStatusEnums::NO_SHOW->value,
            ])
            ->when($ignoreAppointmentId, function ($query) use ($ignoreAppointmentId) {
                $query->where('id', '!=', $ignoreAppointmentId);
            })
            ->exists();

        if ($clash) {
            throw new PatientAppException('You already have an appointment at that time.', 409);
        }
    }

    /**
     * Refuse a check in tapped outside the window around the appointment.
     *
     * @throws \App\Exceptions\PatientAppException
     */
    protected function assertCheckInWindowIsOpen(Appointment $record): void
    {
        $startsAt = $record->starts_at;

        if (!$startsAt) {
            throw new PatientAppException('This appointment has no scheduled time to check in against.', 422);
        }

        $opens = (clone $startsAt)->subMinutes((int) config('patient_app.appointments.check_in_opens_minutes_before'));
        $closes = (clone $startsAt)->addMinutes((int) config('patient_app.appointments.check_in_closes_minutes_after'));
        $now = Carbon::now();

        if ($now->lt($opens)) {
            throw new PatientAppException(
                'Check in opens at ' . $opens->format('h:i A') . ' on ' . $opens->format('D, j M Y') . '.',
                422
            );
        }

        if ($now->gt($closes)) {
            throw new PatientAppException('The check in window for this appointment has closed. Please speak to the front desk.', 422);
        }
    }

    /**
     * Roughly how long the patient will be waiting, from the queue in front of
     * them: everybody checked in today for the same doctor, ahead of them.
     */
    protected function estimatedWaitMinutes(Appointment $record): int
    {
        $ahead = Appointment::query()
            ->forTenant($this->context->tenantUuid())
            ->where('doctor_id', $record->doctor_id)
            ->whereDate('date', $record->date->format('Y-m-d'))
            ->where('status', AppointmentStatusEnums::CHECKED_IN->value)
            ->where('id', '!=', $record->id)
            ->when($record->checked_in_at, function ($query) use ($record) {
                $query->where('checked_in_at', '<', $record->checked_in_at);
            })
            ->count();

        $perPatient = (int) config('patient_app.appointments.average_service_minutes');

        // A patient at the front of the queue is still told to expect one slot
        // of waiting rather than none.
        return max($perPatient, $ahead * $perPatient);
    }

    /**
     * The visit type a consultation type books as.
     *
     * @throws \App\Exceptions\PatientAppException
     */
    protected function visitTypeFor(string $consultationType): string
    {
        $visitType = Appointment::CONSULTATION_TYPES[$consultationType] ?? null;

        if (!$visitType) {
            throw new PatientAppException('Please choose how you would like to see the doctor.', 422);
        }

        return $visitType;
    }

    /**
     * Whether a visit type is held over video.
     */
    protected function isVirtual(string $visitType): bool
    {
        return in_array($visitType, Appointment::VIRTUAL_VISIT_TYPES, true);
    }

    /**
     * How long a slot runs for, spelled the way the schedule stores it.
     */
    protected function defaultDuration(): string
    {
        return (int) config('patient_app.appointments.slot_minutes') . ' minutes';
    }

    /**
     * Build the next human readable appointment reference.
     */
    protected function generateAppointmentNo(): string
    {
        return 'APT-' . Carbon::now()->format('Ymd') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Normalize an incoming time into the H:i:s the column stores.
     *
     * @param  string  $time
     * @return string
     */
    protected function normalizeTime($time): string
    {
        return Carbon::parse($time)->format('H:i:s');
    }

    /**
     * Tell the front desk something happened to an appointment.
     *
     * Never allowed to bring the action down with it: a patient who cancelled
     * has cancelled, whether or not the notification row was written.
     */
    protected function notifyHospital(Appointment $record, string $title, string $message): void
    {
        try {
            Notification::create([
                'user_id' => $this->context->user()->id,
                'tenant_domain' => $this->context->tenant()->domain,
                'title' => $title,
                'message' => $message,
                'role' => 'Record',
            ]);
        } catch (\Throwable $th) {
            Log::warning('Could not notify the hospital about a patient app appointment change.', [
                'appointment_id' => $record->id,
                'title' => $title,
                'exception' => $th->getMessage(),
            ]);
        }
    }

    /**
     * Write an audit log entry for an appointment action.
     *
     * @param  \App\Models\Appointment  $record
     * @param  string  $action
     * @param  string  $logName
     * @param  string  $description
     * @param  array  $oldData
     * @param  array  $newData
     * @return void
     */
    protected function log($record, $action, $logName, $description, array $oldData = [], array $newData = []): void
    {
        try {
            GeneralHelper::storeAuditLog([
                'causer_id' => optional($this->context->user())->id,
                'action_id' => $record->id,
                'action' => $action,
                'action_type' => "Models\Appointment",
                'log_name' => $logName,
                'description' => $description,
                'module_accessed' => ListModuleEnums::Appointment,
                'old_data' => $oldData,
                'new_data' => $newData,
            ]);
        } catch (\Throwable $th) {
            Log::warning('Could not write the audit log for a patient app appointment action.', [
                'appointment_id' => $record->id,
                'action' => $action,
                'exception' => $th->getMessage(),
            ]);
        }
    }

    /**
     * Present the patient attached to an appointment.
     */
    protected function patientName($record): string
    {
        $patient = $record->patient ?: $this->context->patient();

        return trim(optional($patient)->firstname . ' ' . optional($patient)->lastname) ?: 'A patient';
    }

    /**
     * Present the doctor attached to an appointment.
     */
    protected function doctorName($record): ?string
    {
        $doctor = $record->doctor;

        if (!$doctor) {
            return null;
        }

        return $doctor->fullname ?: trim($doctor->first_name . ' ' . $doctor->last_name);
    }

    /**
     * Present an appointment's moment inside a notification sentence.
     */
    protected function readableMoment($record): string
    {
        $startsAt = $record->starts_at;

        return $startsAt ? $startsAt->format('D, j M Y \a\t h:i A') : 'an unscheduled time';
    }
}
