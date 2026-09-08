<?php

namespace App\Services\Revamp;

use App\Enums\AppointmentStatusEnums;
use App\Enums\ListModuleEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Class AppointmentService
 *
 * This class provides services related to Appointment operations and acts as a
 * layer between the Controller and the Appointment model.
 */
class AppointmentService
{
    /**
     * The relations every appointment payload is built from.
     *
     * @var array<int, string>
     */
    protected array $relations = ['patient', 'department', 'doctor'];

    /**
     * Retrieve the appointment schedule of the current tenant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
     */
    public function overview($request)
    {
        $tenantUuid = $this->tenantUuid($request);

        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);

        $records = Appointment::query()
            ->forTenant($tenantUuid)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $search = $request['search_param'];
                $query->where(function ($q) use ($search) {
                    $q->where('appointment_no', 'LIKE', '%' . $search . '%')
                        ->orWhereRelation('patient', 'firstname', 'LIKE', '%' . $search . '%')
                        ->orWhereRelation('patient', 'lastname', 'LIKE', '%' . $search . '%')
                        ->orWhereRelation('patient', 'patientno', 'LIKE', '%' . $search . '%')
                        ->orWhereRelation('patient', 'cardno', 'LIKE', '%' . $search . '%');
                });
            })
            ->when(!empty($request['department_id']), function ($query) use ($request) {
                $query->where('department_id', $request['department_id']);
            })
            ->when(!empty($request['doctor_id']), function ($query) use ($request) {
                $query->where('doctor_id', $request['doctor_id']);
            })
            ->when(!empty($request['status']), function ($query) use ($request) {
                $query->where('status', $request['status']);
            })
            ->when(!empty($request['appointment_type']), function ($query) use ($request) {
                $query->where('appointment_type', $request['appointment_type']);
            })
            ->when(!empty($request['visit_type']), function ($query) use ($request) {
                $query->where('visit_type', $request['visit_type']);
            })
            ->when(!empty($request['patient_id']), function ($query) use ($request) {
                $query->where('patient_id', $request['patient_id']);
            })
            ->when(!empty($request['date']), function ($query) use ($request) {
                $query->whereDate('date', Carbon::parse($request['date'])->toDateString());
            })
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('date', [
                    Carbon::parse($request->start_date)->toDateString(),
                    Carbon::parse($request->end_date)->toDateString(),
                ]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('date', $dateFilter);
            })
            ->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('date', 'ASC')->orderBy('time', 'ASC');
            })
            ->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('date', 'DESC')->orderBy('time', 'DESC');
            })
            ->with($this->relations);

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $records->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $records->orderBy('id', 'DESC')->get();
    }

    /**
     * Build the stat cards that sit above the appointment schedule.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function stats($request)
    {
        $query = Appointment::query()->forTenant($this->tenantUuid($request));
        $today = Carbon::now()->toDateString();

        return [
            'todaysAppointments' => (clone $query)->whereDate('date', $today)->count(),
            'scheduledAppointments' => (clone $query)->where('status', AppointmentStatusEnums::SCHEDULED->value)->count(),
            'checkedInAppointments' => (clone $query)->where('status', AppointmentStatusEnums::CHECKED_IN->value)->count(),
            'completedAppointments' => (clone $query)->where('status', AppointmentStatusEnums::COMPLETED->value)->count(),
        ];
    }

    /**
     * Book an appointment for the current tenant.
     *
     * @param  \App\Http\Requests\StoreAppointmentRequest  $request
     * @return \App\Models\Appointment
     */
    public function store(StoreAppointmentRequest $request)
    {
        $tenantUuid = $this->tenantUuid($request);
        $validated = $request->validated();
        $currentUser = GeneralHelper::userInstance();

        DB::connection('tenant')->beginTransaction();

        try {
            $record = Appointment::create([
                'tenant_uuid' => $tenantUuid,
                'appointment_no' => $this->generateAppointmentNo(),
                'patient_id' => $validated['patient_id'],
                'visit_id' => $validated['visit_id'] ?? null,
                'appointment_type' => $validated['appointment_type'],
                'department_id' => $validated['department_id'],
                'doctor_id' => $validated['doctor_id'],
                'visit_type' => $validated['visit_type'],
                'date' => Carbon::parse($validated['date'])->toDateString(),
                'time' => $this->normalizeTime($validated['time']),
                'duration' => $validated['duration'] ?? null,
                'reason' => $validated['reason'] ?? null,
                'status' => $validated['status'] ?? AppointmentStatusEnums::SCHEDULED->value,
                'created_by' => optional($currentUser)->id,
            ]);

            DB::connection('tenant')->commit();
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            throw $th;
        }

        $record->load($this->relations);

        $this->log($record, 'Create', 'Appointment created successfully', sprintf(
            '%s booked a new appointment (%s) for %s',
            $this->causerName($currentUser),
            $record->appointment_no,
            $this->patientName($record)
        ));

        return $record;
    }

    /**
     * Retrieve a single appointment of the current tenant.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request|null  $request
     * @return \App\Models\Appointment
     */
    public function show($id, $request = null)
    {
        return $this->findOrFail($id, $request, ['patient.visits_recent', 'department', 'doctor', 'visit']);
    }

    /**
     * Update an appointment of the current tenant.
     *
     * @param  \App\Http\Requests\UpdateAppointmentRequest  $request
     * @param  int  $id
     * @return \App\Models\Appointment
     */
    public function update(UpdateAppointmentRequest $request, $id)
    {
        $record = $this->findOrFail($id, $request);
        $validated = $request->validated();
        $currentUser = GeneralHelper::userInstance();
        $oldData = $record->toArray();

        if (array_key_exists('date', $validated)) {
            $validated['date'] = Carbon::parse($validated['date'])->toDateString();
        }

        if (array_key_exists('time', $validated)) {
            $validated['time'] = $this->normalizeTime($validated['time']);
        }

        DB::connection('tenant')->beginTransaction();

        try {
            $record->update($validated);

            DB::connection('tenant')->commit();
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            throw $th;
        }

        $record->refresh()->load($this->relations);

        $this->log($record, 'Update', 'Appointment updated successfully', sprintf(
            '%s updated the appointment (%s) for %s',
            $this->causerName($currentUser),
            $record->appointment_no,
            $this->patientName($record)
        ), $oldData, $record->toArray());

        return $record;
    }

    /**
     * Soft delete an appointment of the current tenant.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request|null  $request
     * @return \App\Models\Appointment
     */
    public function destroy($id, $request = null)
    {
        $record = $this->findOrFail($id, $request);
        $currentUser = GeneralHelper::userInstance();
        $oldData = $record->toArray();

        $record->delete();

        $this->log($record, 'Delete', 'Appointment deleted successfully', sprintf(
            '%s deleted the appointment (%s) for %s',
            $this->causerName($currentUser),
            $record->appointment_no,
            $this->patientName($record)
        ), $oldData, []);

        return $record;
    }

    /**
     * Export the given appointments in the requested format.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator  $records
     * @param  string  $format
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function export($records, $format)
    {
        $exportData = collect($records)->map(function ($appointment) {
            return [
                // 'Appointment ID' => $appointment->appointment_no,
                'Date' => optional($appointment->date)->format('Y-m-d'),
                'Time' => $appointment->time,
                'Patient' => $this->patientName($appointment),
                'Patient No' => optional($appointment->patient)->patientno,
                'Doctor' => $this->doctorName($appointment),
                'Department' => optional($appointment->department)->name,
                // 'Appointment Type' => $appointment->appointment_type,
                'Visit Type' => $appointment->visit_type,
                // 'Reason' => $appointment->reason,
                'Status' => $appointment->status,
                // 'Date Created' => optional($appointment->created_at)->format('Y-m-d H:i'),
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'appointments.csv');
        }

        if (strtolower($format) === 'pdf') {
            return ExportHelper::downloadPdf($exportData, 'appointments.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    /**
     * Resolve an appointment scoped to the current tenant.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request|null  $request
     * @param  array<int, string>|null  $relations
     * @return \App\Models\Appointment
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function findOrFail($id, $request = null, ?array $relations = null)
    {
        return Appointment::query()
            ->forTenant($this->tenantUuid($request))
            ->with($relations ?: $this->relations)
            ->findOrFail($id);
    }

    /**
     * Build the next human readable appointment reference.
     *
     * @return string
     */
    protected function generateAppointmentNo()
    {
        return 'APT-' . Carbon::now()->format('Ymd') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Normalize an incoming time into the H:i:s the column stores.
     *
     * @param  string  $time
     * @return string
     */
    protected function normalizeTime($time)
    {
        return Carbon::parse($time)->format('H:i:s');
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
    protected function log($record, $action, $logName, $description, array $oldData = [], array $newData = [])
    {
        $currentUser = GeneralHelper::userInstance();

        GeneralHelper::storeAuditLog([
            'causer_id' => optional($currentUser)->id,
            'action_id' => $record->id,
            'action' => $action,
            'action_type' => "Models\Appointment",
            'log_name' => $logName,
            'description' => $description,
            'module_accessed' => ListModuleEnums::Appointment,
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);
    }

    /**
     * Present the acting user in an audit log description.
     *
     * @param  \App\Models\User|null  $user
     * @return string
     */
    protected function causerName($user)
    {
        if (!$user) {
            return 'System';
        }

        $name = $user->fullname ?: trim($user->first_name . ' ' . $user->last_name);

        return $name !== '' ? $name : ($user->email ?? 'System');
    }

    /**
     * Present the patient attached to an appointment.
     *
     * @param  \App\Models\Appointment  $record
     * @return string
     */
    protected function patientName($record)
    {
        $patient = $record->patient;

        return trim(optional($patient)->firstname . ' ' . optional($patient)->lastname) ?: 'the patient';
    }

    /**
     * Present the doctor attached to an appointment.
     *
     * @param  \App\Models\Appointment  $record
     * @return string|null
     */
    protected function doctorName($record)
    {
        $doctor = $record->doctor;

        if (!$doctor) {
            return null;
        }

        return $doctor->fullname ?: trim($doctor->first_name . ' ' . $doctor->last_name);
    }

    /**
     * Resolve the tenant uuid carried by the request.
     *
     * @param  \Illuminate\Http\Request|null  $request
     * @return string|null
     */
    protected function tenantUuid($request = null)
    {
        $request = $request ?: request();

        return $request->header('X-Tenant-ID');
    }
}
