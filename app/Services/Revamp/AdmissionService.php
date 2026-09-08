<?php

namespace App\Services\Revamp;

use App\Enums\AdmissionStatusEnums;
use App\Enums\GeneralEnums;
use App\Enums\ListModuleEnums;
use App\Enums\PatientVisitStatusEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Models\AdmittedPatient;
use App\Models\Bed;
use App\Models\BillingService;
use App\Models\Department;
use App\Models\EmergencyContact;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Ward;
use App\Models\BillingLog;
use App\Models\BillingLogDetail;
use App\Models\CareNote;
use App\Models\DrugChart;
use App\Models\Service;
use App\Models\ServiceUnit;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Models\Tenant;

class AdmissionService
{
    /**
     * The relations every admission payload is built from.
     *
     * @var array<int, string>
     */
    protected array $relations = ['patient', 'visit', 'ward', 'department', 'doctor'];

    /**
     * The tabs of the admissions listing mapped to the statuses they hold.
     *
     * @var array<string, array<int, string>>
     */
    protected array $tabs = [
        'all' => [],
        'active' => [AdmissionStatusEnums::ADMITTED->value],
        'scheduled' => [AdmissionStatusEnums::SCHEDULED->value],
        'discharged' => [AdmissionStatusEnums::DISCHARGED->value],
        'cancelled' => [AdmissionStatusEnums::CANCELLED->value],
        'pending' => [AdmissionStatusEnums::PENDING->value],
    ];

    /**
     * Retrieve the admissions of the current tenant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
     */
    public function overview($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $tabStatuses = $this->statusesForTab($request['tab'] ?? null);

        $query = AdmittedPatient::query()->forTenant($tenantId)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $search = '%' . $request['search_param'] . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('admission_no', 'LIKE', $search)
                        ->orWhere('bed', 'LIKE', $search)
                        ->orWhereRelation('patient', 'cardno', 'LIKE', $search)
                        ->orWhereRelation('patient', 'patientno', 'LIKE', $search)
                        ->orWhereRelation('patient', 'firstname', 'LIKE', $search)
                        ->orWhereRelation('patient', 'lastname', 'LIKE', $search)
                        ->orWhereRelation('ward', 'name', 'LIKE', $search)
                        ->orWhereRelation('department', 'name', 'LIKE', $search);
                });
            })
            ->when(!empty($tabStatuses), function ($query) use ($tabStatuses) {
                $query->whereIn('status', $tabStatuses);
            })
            ->when(!empty($request['status']), function ($query) use ($request) {
                $query->where('status', $request['status']);
            })
            ->when(!empty($request['admission_type']), function ($query) use ($request) {
                $query->where('admission_type', $request['admission_type']);
            })
            ->when(!empty($request['department_id']), function ($query) use ($request) {
                $query->where('department_id', $request['department_id']);
            })
            ->when(!empty($request['doctor_id']), function ($query) use ($request) {
                $query->where('doctor_id', $request['doctor_id']);
            })
            ->when(!empty($request['ward_id']), function ($query) use ($request) {
                $query->where('ward_id', $request['ward_id']);
            })
            ->when(!empty($request['patient_id']), function ($query) use ($request) {
                $query->where('patient_id', $request['patient_id']);
            })
            ->when(!empty($request['payment_status']), function ($query) use ($request) {
                $query->where('payment_status', $request['payment_status']);
            })
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('date_admitted', [
                    Carbon::parse($request->start_date)->toDateString(),
                    Carbon::parse($request->end_date)->toDateString(),
                ]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('date_admitted', 'ASC');
            })->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('date_admitted', 'DESC');
            })->with($this->relations);

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $query->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $query->orderBy('id', 'DESC')->get();
    }

    /**
     * Build the counters that sit above the admissions listing.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function stats($request)
    {
        $admissionQuery = AdmittedPatient::query()->forTenant($request->header('X-Tenant-ID'));

        return [
            'total_number_of_admitted_patients' => (clone $admissionQuery)->count(),
            'patients_pending_admission' => (clone $admissionQuery)->where('status', AdmissionStatusEnums::PENDING->value)->count(),
            'number_of_patients_admission' => (clone $admissionQuery)->where('status', AdmissionStatusEnums::ADMITTED->value)->count(),
            'scheduled_admissions' => (clone $admissionQuery)->where('status', AdmissionStatusEnums::SCHEDULED->value)->count(),
            'discharged_patients' => (clone $admissionQuery)->where('status', AdmissionStatusEnums::DISCHARGED->value)->count(),
            'cancelled_admissions' => (clone $admissionQuery)->where('status', AdmissionStatusEnums::CANCELLED->value)->count(),
        ];
    }

    /**
     * Export the given admissions in the requested format.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator  $records
     * @param  string  $format
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function export($records, $format)
    {
        $exportData = collect($records)->map(function ($admission) {
            return [
                'Admission No' => $admission->admission_no ?? 'N/A',
                'Patient Name' => trim(optional($admission->patient)->firstname . ' ' . optional($admission->patient)->lastname) ?: 'N/A',
                'Card No' => optional($admission->patient)->cardno ?? 'N/A',
                'Patient No' => optional($admission->patient)->patientno ?? 'N/A',
                'Admission Type' => $admission->admission_type ?? 'N/A',
                'Department' => optional($admission->department)->name ?? 'N/A',
                'Doctor' => $this->userName($admission->doctor) ?? 'N/A',
                'Ward And Bed' => $admission->ward_bed ?? 'N/A',
                'Admission Date' => optional($admission->date_admitted)->format('Y-m-d') ?? 'N/A',
                'Expected Admission Date' => optional($admission->expected_admission_date)->format('Y-m-d') ?? 'N/A',
                'Discharged Date' => optional($admission->date_discharged)->format('Y-m-d') ?? 'N/A',
                'Cancellation Reason' => $admission->cancellation_reason ?? 'N/A',
                'Status' => $admission->status ?? 'N/A',
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'patient_admissions.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('patient_admissions.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    /**
     * Retrieve a single admission of the current tenant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \App\Models\AdmittedPatient|null
     */
    public function getAdmissionDetails($request, $id)
    {
        return AdmittedPatient::query()
            ->forTenant($request->header('X-Tenant-ID'))
            ->with([...$this->relations, 'bedSpace', 'billing', 'admittedBy', 'dischargedBy', 'cancelledBy'])
            ->find($id);
    }

    /**
     * A patient as the Patient Information tab of the admitted patient screen
     * reads them: their personal details, the ward and bed they are on and
     * their emergency contact.
     *
     * The admission the profile describes is attached as a currentAdmission
     * relation — the stay the patient is currently on, falling back to their
     * most recent one so a discharged patient still shows where they were.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id  The patient id.
     * @return \App\Models\Patient|null
     */
    public function getAdmittedPatientProfile($request, $id)
    {
        $patient = Patient::query()
            ->with(['nextOfKin', 'emergencyContact', 'visits_recent'])
            ->find($id);

        if (!$patient) {
            return null;
        }

        $admissions = AdmittedPatient::query()
            ->forTenant($request->header('X-Tenant-ID'))
            ->with(['ward', 'bedSpace', 'cancelledBy'])
            ->where('patient_id', $patient->id);

        $admission = (clone $admissions)
            ->where('status', AdmissionStatusEnums::ADMITTED->value)
            ->latest('id')
            ->first()
            ?: $admissions->latest('id')->first();

        $patient->setRelation('currentAdmission', $admission);

        return $patient;
    }

    /**
     * The wards of the current tenant with their bed capacity.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getWards($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        return Ward::query()->where('tenant_id', $tenantId)->with('bed')->get();
    }

    /**
     * The bed spaces of a ward, flagged with the ones already taken.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $wardId
     * @return array
     */
    public function getWardBedSpaces($request, $wardId)
    {
        $tenantId = $request->header('X-Tenant-ID');

        $ward = Ward::where('tenant_id', $tenantId)->with('bed')->find($wardId);

        if (!$ward) {
            throw new \Exception("Ward not found.");
        }

        $bed = $ward->bed;
        $capacity = $bed ? (int) $bed->bed_number : 0;

        // The bed labels already held by a patient in this ward.
        $taken = AdmittedPatient::query()
            ->forTenant($tenantId)
            ->where('ward_id', $ward->id)
            ->where('status', AdmissionStatusEnums::ADMITTED->value)
            ->pluck('bed')
            ->filter()
            ->all();

        $spaces = [];
        for ($number = 1; $number <= $capacity; $number++) {
            $label = 'Bed ' . $number;
            $spaces[] = [
                'bed_id' => optional($bed)->id,
                'label' => $label,
                'occupied' => in_array($label, $taken, true),
            ];
        }

        return [
            'ward' => [
                'id' => $ward->id,
                'name' => $ward->name,
                'type' => $ward->type,
                'gender' => $ward->gender,
                'cost' => $ward->cost,
            ],
            'total_beds' => $capacity,
            'available_beds' => $bed ? (int) $bed->available_bed_number : 0,
            'bed_spaces' => $spaces,
        ];
    }

    /**
     * The option lists the admission forms are built from.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function getAdmissionOptions($request)
    {
        // The X-Tenant-ID header carries the tenant uuid, which is what the
        // departments table scopes on.
        $tenantId = $request->header('X-Tenant-ID');

        return [
            'admission_types' => AdmittedPatient::TYPES,
            'statuses' => AdmittedPatient::STATUSES,
            'payment_statuses' => AdmittedPatient::PAYMENT_STATUSES,
            'departments' => Department::query()
                ->forTenant($tenantId)
                ->where('status', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'wards' => Ward::query()
                ->where('tenant_id', $tenantId)
                ->with('bed')
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * Admit a patient.
     *
     * Completes an admission already initiated from a consultation when an
     * admission_id is supplied, otherwise raises a fresh one from the new
     * admission form. Either way the patient is billed for the admission and
     * takes a bed in the chosen ward.
     *
     * @param  \App\Http\Requests\Admission\StoreAdmissionRequest  $request
     * @return \App\Models\AdmittedPatient
     */
    public function admitPatient($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $data = $request->validated();
        $currentUser = Auth::user();

        $admission = DB::connection('tenant')->transaction(function () use ($data, $tenantId, $currentUser) {
            $admission = $this->resolveAdmission($data, $tenantId);

            if ($admission && in_array($admission->status, [
                AdmissionStatusEnums::ADMITTED->value,
                AdmissionStatusEnums::DISCHARGED->value,
                AdmissionStatusEnums::CANCELLED->value,
            ], true)) {
                throw new \Exception("This admission has already been {$admission->status}.");
            }

            // Fall back to the admission the consultation raised whenever the
            // form leaves these out (or sends them empty).
            $patientId = !empty($data['patient_id']) ? $data['patient_id'] : optional($admission)->patient_id;
            $visitId = !empty($data['visit_id']) ? $data['visit_id'] : optional($admission)->visit_id;

            $patient = Patient::find($patientId);
            if (!$patient) {
                throw new \Exception("Patient record not found for this admission.");
            }

            [$ward, $bed, $bedLabel] = $this->reserveBed($tenantId, $data['ward_id'], $data['bed_id'] ?? null, $data['bed_space'] ?? null);

            $attributes = $this->admissionAttributes($data, [
                'tenant_id' => $tenantId,
                'patient_id' => $patient->id,
                'visit_id' => $visitId,
                'ward_id' => $ward->id,
                'bed_id' => optional($bed)->id,
                'bed' => $bedLabel,
                'admitted_by' => optional($currentUser)->id,
                'date_admitted' => isset($data['admission_date'])
                    ? Carbon::parse($data['admission_date'])->toDateString()
                    : now()->toDateString(),
                'admission_time' => $this->normalizeTime($data['admission_time'] ?? null) ?: now()->format('H:i:s'),
                'status' => AdmissionStatusEnums::ADMITTED->value,
            ]);

            $admission = $this->persistAdmission($admission, $attributes, $tenantId, $currentUser);

            $this->billAdmission($admission, $patient, $ward, $tenantId, $currentUser);

            $this->syncPatientStatus($admission, PatientVisitStatusEnums::ADMITTED->value);

            return $admission;
        });

        $admission->refresh()->load($this->relations);

        $this->log($admission, 'Create', 'Patient admitted successfully', sprintf(
            '%s admitted %s (%s)',
            $this->causerName($currentUser),
            $this->patientName($admission),
            $admission->admission_no
        ), [], $admission->toArray());

        return $admission;
    }

    /**
     * Schedule an admission for a future date.
     *
     * Nothing is billed and no bed is taken until the patient is admitted; the
     * ward and bed captured here are only preferences.
     *
     * @param  \App\Http\Requests\Admission\ScheduleAdmissionRequest  $request
     * @return \App\Models\AdmittedPatient
     */
    public function scheduleAdmission($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $data = $request->validated();
        $currentUser = Auth::user();

        $admission = DB::connection('tenant')->transaction(function () use ($data, $tenantId, $currentUser) {
            $admission = $this->resolveAdmission($data, $tenantId);

            if ($admission && in_array($admission->status, [
                AdmissionStatusEnums::ADMITTED->value,
                AdmissionStatusEnums::DISCHARGED->value,
                AdmissionStatusEnums::CANCELLED->value,
            ], true)) {
                throw new \Exception("This admission has already been {$admission->status} and can no longer be scheduled.");
            }

            // Fall back to the admission the consultation raised whenever the
            // form leaves these out (or sends them empty).
            $patientId = !empty($data['patient_id']) ? $data['patient_id'] : optional($admission)->patient_id;
            $visitId = !empty($data['visit_id']) ? $data['visit_id'] : optional($admission)->visit_id;

            $patient = Patient::find($patientId);
            if (!$patient) {
                throw new \Exception("Patient record not found for this admission.");
            }

            $ward = null;
            if (!empty($data['ward_id'])) {
                $ward = Ward::where('tenant_id', $tenantId)->find($data['ward_id']);
                if (!$ward) {
                    throw new \Exception("Ward not found.");
                }
            }

            $attributes = $this->admissionAttributes($data, [
                'tenant_id' => $tenantId,
                'patient_id' => $patient->id,
                'visit_id' => $visitId,
                'ward_id' => optional($ward)->id,
                'bed_id' => $data['bed_id'] ?? null,
                'bed' => $data['bed_space'] ?? null,
                'expected_admission_date' => Carbon::parse($data['expected_admission_date'])->toDateString(),
                'expected_admission_time' => $this->normalizeTime($data['expected_admission_time']),
                'status' => AdmissionStatusEnums::SCHEDULED->value,
            ]);

            return $this->persistAdmission($admission, $attributes, $tenantId, $currentUser);
        });

        $admission->refresh()->load($this->relations);

        $this->log($admission, 'Create', 'Admission scheduled successfully', sprintf(
            '%s scheduled an admission (%s) for %s',
            $this->causerName($currentUser),
            $admission->admission_no,
            $this->patientName($admission)
        ), [], $admission->toArray());

        return $admission;
    }

    /**
     * Admit a patient through the emergency route.
     *
     * The patient may be walked in unregistered, in which case they are
     * registered from the identity fields on the form and a visit is opened
     * for them before the admission is raised.
     *
     * @param  \App\Http\Requests\Admission\EmergencyAdmissionRequest  $request
     * @return \App\Models\AdmittedPatient
     */
    public function emergencyAdmission($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $data = $request->validated();
        $currentUser = Auth::user();

        $admission = DB::connection('tenant')->transaction(function () use ($data, $tenantId, $currentUser) {
            $patient = !empty($data['patient_id'])
                ? Patient::find($data['patient_id'])
                : $this->registerEmergencyPatient($data, $tenantId, $currentUser);

            if (!$patient) {
                throw new \Exception("Patient record not found for this admission.");
            }

            $visit = !empty($data['visit_id'])
                ? PatientVisit::find($data['visit_id'])
                : $this->openEmergencyVisit($patient, $data, $tenantId, $currentUser);

            [$ward, $bed, $bedLabel] = $this->reserveBed($tenantId, $data['ward_id'], $data['bed_id'] ?? null, $data['bed_space'] ?? null);

            $attributes = $this->admissionAttributes($data, [
                'tenant_id' => $tenantId,
                'patient_id' => $patient->id,
                'visit_id' => optional($visit)->id,
                'ward_id' => $ward->id,
                'bed_id' => optional($bed)->id,
                'bed' => $bedLabel,
                'admission_type' => $data['admission_type'] ?? 'Emergency',
                'admitted_by' => optional($currentUser)->id,
                'date_admitted' => isset($data['admission_date'])
                    ? Carbon::parse($data['admission_date'])->toDateString()
                    : now()->toDateString(),
                'admission_time' => $this->normalizeTime($data['admission_time'] ?? null) ?: now()->format('H:i:s'),
                'status' => AdmissionStatusEnums::ADMITTED->value,
            ]);

            $admission = $this->persistAdmission(null, $attributes, $tenantId, $currentUser);

            $this->billAdmission($admission, $patient, $ward, $tenantId, $currentUser, true);

            $this->syncPatientStatus($admission, PatientVisitStatusEnums::ADMITTED->value);

            return $admission;
        });

        $admission->refresh()->load($this->relations);

        $this->log($admission, 'Create', 'Emergency admission created successfully', sprintf(
            '%s raised an emergency admission (%s) for %s',
            $this->causerName($currentUser),
            $admission->admission_no,
            $this->patientName($admission)
        ), [], $admission->toArray());

        return $admission;
    }

    /**
     * Update the clinical and financial detail of an existing admission.
     *
     * @param  \App\Http\Requests\Admission\UpdateAdmissionRequest  $request
     * @param  int  $id
     * @return \App\Models\AdmittedPatient
     */
    public function updateAdmission($request, $id)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $currentUser = Auth::user();

        $admission = AdmittedPatient::query()->forTenant($tenantId)->find($id);

        if (!$admission) {
            throw new \Exception("Admission record not found.");
        }

        if ($admission->status === AdmissionStatusEnums::CANCELLED->value) {
            throw new \Exception("A cancelled admission can no longer be updated.");
        }

        $data = $request->validated();
        $oldData = $admission->toArray();

        // Only the fields actually sent are touched — a field sent as null is
        // cleared, one that is absent is left alone.
        $attributes = [];
        $directFields = [
            'admission_type',
            'referred_by',
            'reason',
            'notes',
            'department_id',
            'doctor_id',
            'payer_type',
            'payment_status',
        ];

        foreach ($directFields as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        if (array_key_exists('deposit_amount', $data)) {
            $attributes['deposit_amount'] = $data['deposit_amount'] ?? 0;
        }

        if (array_key_exists('admission_date', $data)) {
            $attributes['date_admitted'] = $data['admission_date']
                ? Carbon::parse($data['admission_date'])->toDateString()
                : null;
        }

        if (array_key_exists('admission_time', $data)) {
            $attributes['admission_time'] = $this->normalizeTime($data['admission_time']);
        }

        if (array_key_exists('expected_admission_date', $data)) {
            $attributes['expected_admission_date'] = $data['expected_admission_date']
                ? Carbon::parse($data['expected_admission_date'])->toDateString()
                : null;
        }

        if (array_key_exists('expected_admission_time', $data)) {
            $attributes['expected_admission_time'] = $this->normalizeTime($data['expected_admission_time']);
        }

        $admission->update($attributes);
        $admission->refresh()->load($this->relations);

        $this->log($admission, 'Update', 'Admission updated successfully', sprintf(
            '%s updated the admission (%s) for %s',
            $this->causerName($currentUser),
            $admission->admission_no,
            $this->patientName($admission)
        ), $oldData, $admission->toArray());

        return $admission;
    }

    /**
     * Move an admitted patient to another ward and bed.
     *
     * @param  \App\Http\Requests\Admission\TransferAdmissionRequest  $request
     * @return \App\Models\AdmittedPatient
     */
    public function transferAdmission($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $data = $request->validated();
        $currentUser = Auth::user();

        $admission = DB::connection('tenant')->transaction(function () use ($data, $tenantId) {
            $admission = AdmittedPatient::query()->forTenant($tenantId)->find($data['admission_id']);

            if (!$admission) {
                throw new \Exception("Admission record not found.");
            }

            if ($admission->status !== AdmissionStatusEnums::ADMITTED->value) {
                throw new \Exception("Only an admitted patient can be transferred.");
            }

            if ((int) $admission->ward_id === (int) $data['ward_id'] && empty($data['bed_space'])) {
                throw new \Exception("The patient is already in this ward.");
            }

            [$ward, $bed, $bedLabel] = $this->reserveBed($tenantId, $data['ward_id'], $data['bed_id'] ?? null, $data['bed_space'] ?? null);

            // Give back the bed the patient is leaving.
            $this->releaseBed($tenantId, $admission);

            $admission->update([
                'ward_id' => $ward->id,
                'bed_id' => optional($bed)->id,
                'bed' => $bedLabel,
                'notes' => $data['reason'] ?? $admission->notes,
            ]);

            return $admission;
        });

        $admission->refresh()->load($this->relations);

        $this->log($admission, 'Update', 'Patient transferred successfully', sprintf(
            '%s transferred %s to %s',
            $this->causerName($currentUser),
            $this->patientName($admission),
            $admission->ward_bed
        ), [], $admission->toArray());

        return $admission;
    }

    /**
     * Cancel a pending or scheduled admission.
     *
     * @param  \App\Http\Requests\Admission\CancelAdmissionRequest  $request
     * @return \App\Models\AdmittedPatient
     */
    public function cancelAdmission($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $data = $request->validated();
        $currentUser = Auth::user();

        $admission = DB::connection('tenant')->transaction(function () use ($data, $tenantId, $currentUser) {
            $admission = AdmittedPatient::query()->forTenant($tenantId)->find($data['admission_id']);

            if (!$admission) {
                throw new \Exception("Admission record not found.");
            }

            if ($admission->status === AdmissionStatusEnums::CANCELLED->value) {
                throw new \Exception("This admission has already been cancelled.");
            }

            if ($admission->status === AdmissionStatusEnums::DISCHARGED->value) {
                throw new \Exception("A discharged admission can no longer be cancelled.");
            }

            // An admitted patient still holds a bed — hand it back before cancelling.
            if ($admission->status === AdmissionStatusEnums::ADMITTED->value) {
                $this->releaseBed($tenantId, $admission);
            }

            $admission->update([
                'status' => AdmissionStatusEnums::CANCELLED->value,
                'cancelled_by' => optional($currentUser)->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $data['cancellation_reason'],
            ]);

            $this->syncPatientStatus($admission, PatientVisitStatusEnums::NOT_ADMITTED->value);

            return $admission;
        });

        $admission->refresh()->load([...$this->relations, 'cancelledBy']);

        $this->log($admission, 'Update', 'Admission cancelled successfully', sprintf(
            '%s cancelled the admission (%s) for %s',
            $this->causerName($currentUser),
            $admission->admission_no,
            $this->patientName($admission)
        ), [], $admission->toArray());

        return $admission;
    }

    /**
     * Discharge an admitted patient and release the bed they hold.
     *
     * @param  \App\Http\Requests\Admission\DischargeAdmissionRequest  $request
     * @return \App\Models\AdmittedPatient
     */
    public function dischargePatient($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $data = $request->validated();
        $currentUser = Auth::user();

        $admission = DB::connection('tenant')->transaction(function () use ($data, $tenantId, $currentUser) {
            $admission = AdmittedPatient::query()->forTenant($tenantId)->find($data['admission_id']);

            if (!$admission) {
                throw new \Exception("Admission record not found.");
            }

            if ($admission->status === AdmissionStatusEnums::DISCHARGED->value) {
                throw new \Exception("This patient has already been discharged.");
            }

            if ($admission->status !== AdmissionStatusEnums::ADMITTED->value) {
                throw new \Exception("Only an admitted patient can be discharged.");
            }

            // Check if patient has any pending payments
            $pendingBills = BillingLog::where('patient_id', $admission->patient_id)
                ->where('visit_id', $admission->visit_id)
                ->whereIn('payment_status', ['Pending', 'Part Paid'])
                ->exists();

            if ($pendingBills) {
                throw new \Exception("Patient has pending payments. All bills must be paid before discharge.");
            }

            $this->releaseBed($tenantId, $admission);

            $admission->update([
                'status' => AdmissionStatusEnums::DISCHARGED->value,
                'discharged_by' => optional($currentUser)->id,
                'date_discharged' => isset($data['date_discharged'])
                    ? Carbon::parse($data['date_discharged'])->toDateString()
                    : now()->toDateString(),
                'discharge_time' => $this->normalizeTime($data['discharge_time'] ?? null) ?: now()->format('H:i:s'),
                'discharge_notes' => $data['discharge_notes'] ?? null,
            ]);

            $this->syncPatientStatus($admission, PatientVisitStatusEnums::DISCHARGED->value);

            return $admission;
        });

        $admission->refresh()->load([...$this->relations, 'dischargedBy']);

        $this->log($admission, 'Update', 'Patient discharged successfully', sprintf(
            '%s discharged %s (%s)',
            $this->causerName($currentUser),
            $this->patientName($admission),
            $admission->admission_no
        ), [], $admission->toArray());

        return $admission;
    }

    /**
     * Resolve the admission an incoming form refers to, if any.
     *
     * @param  array  $data
     * @param  string|null  $tenantId
     * @return \App\Models\AdmittedPatient|null
     */
    protected function resolveAdmission(array $data, $tenantId)
    {
        if (!empty($data['admission_id'])) {
            $admission = AdmittedPatient::query()->forTenant($tenantId)->find($data['admission_id']);

            if (!$admission) {
                throw new \Exception("Admission record not found.");
            }

            return $admission;
        }

        // An admission may already be pending for this visit, raised from the
        // consultation — complete it rather than opening a second one.
        if (!empty($data['visit_id'])) {
            return AdmittedPatient::query()
                ->forTenant($tenantId)
                ->where('visit_id', $data['visit_id'])
                ->where('status', AdmissionStatusEnums::PENDING->value)
                ->first();
        }

        return null;
    }

    /**
     * Build the attributes shared by every admission form.
     *
     * @param  array  $data
     * @param  array  $overrides
     * @return array
     */
    protected function admissionAttributes(array $data, array $overrides = [])
    {
        return array_merge([
            'admission_type' => $data['admission_type'] ?? null,
            'referred_by' => $data['referred_by'] ?? null,
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'doctor_id' => $data['doctor_id'] ?? null,
            'payer_type' => $data['payer_type'] ?? null,
            'deposit_amount' => $data['deposit_amount'] ?? 0,
            'payment_status' => $data['payment_status'] ?? 'Pending',
        ], $overrides);
    }

    /**
     * Create the admission, or complete the one initiated from a consultation.
     *
     * @param  \App\Models\AdmittedPatient|null  $admission
     * @param  array  $attributes
     * @param  string|null  $tenantId
     * @param  \App\Models\User|null  $currentUser
     * @return \App\Models\AdmittedPatient
     */
    protected function persistAdmission($admission, array $attributes, $tenantId, $currentUser)
    {
        if ($admission) {
            $attributes['admission_no'] = $admission->admission_no ?: $this->generateAdmissionNo();
            $admission->update($attributes);

            return $admission;
        }

        return AdmittedPatient::create(array_merge($attributes, [
            'admission_no' => $this->generateAdmissionNo(),
            'created_by' => optional($currentUser)->id,
        ]));
    }

    /**
     * Take a bed in the given ward.
     *
     * @param  string|null  $tenantId
     * @param  int  $wardId
     * @param  int|null  $bedId
     * @param  string|null  $bedSpace
     * @return array{0: \App\Models\Ward, 1: \App\Models\Bed|null, 2: string|null}
     */
    protected function reserveBed($tenantId, $wardId, $bedId = null, $bedSpace = null)
    {
        $ward = Ward::where('tenant_id', $tenantId)->with('bed')->find($wardId);

        if (!$ward) {
            throw new \Exception("Ward not found.");
        }

        $bed = $bedId
            ? Bed::where('ward_id', $ward->id)->find($bedId)
            : $ward->bed;

        if (!$bed) {
            throw new \Exception("No bed space configured for this ward.");
        }

        if ($bed->available_bed_number <= 0) {
            throw new \Exception("No available beds in this ward.");
        }

        // Bed assignment name: "Bed X". For example, if bed_number is 5 and
        // available_bed_number is 5, the first assigned is "Bed 1".
        $label = $bedSpace ?: 'Bed ' . ($bed->bed_number - $bed->available_bed_number + 1);

        $bed->available_bed_number = max(0, $bed->available_bed_number - 1);
        $bed->occupied = $bed->available_bed_number === 0;
        $bed->save();

        return [$ward, $bed, $label];
    }

    /**
     * Give back the bed an admission holds.
     *
     * @param  string|null  $tenantId
     * @param  \App\Models\AdmittedPatient  $admission
     * @return void
     */
    protected function releaseBed($tenantId, $admission)
    {
        $bed = $admission->bed_id
            ? Bed::find($admission->bed_id)
            : optional(Ward::where('tenant_id', $tenantId)->with('bed')->find($admission->ward_id))->bed;

        if (!$bed) {
            return;
        }

        $bed->available_bed_number = min((int) $bed->bed_number, (int) $bed->available_bed_number + 1);
        $bed->occupied = $bed->available_bed_number === 0;
        $bed->save();
    }

    /**
     * Raise the invoice for an admission: the admission fee taken from the
     * billing services catalogue, plus the cost of the ward the patient takes.
     *
     * @param  \App\Models\AdmittedPatient  $admission
     * @param  \App\Models\Patient  $patient
     * @param  \App\Models\Ward  $ward
     * @param  string|null  $tenantId
     * @param  \App\Models\User|null  $currentUser
     * @param  bool  $isEmergency
     * @return \App\Models\BillingLog|null
     */
    protected function billAdmission($admission, $patient, $ward, $tenantId, $currentUser, $isEmergency = false)
    {
        $billingService = $this->resolveAdmissionBillingService($admission->admission_type, $tenantId, $isEmergency);

        $admissionFee = $billingService ? (float) $billingService->price : 0.00;
        $wardCost = (float) optional($ward)->cost;
        $total = $admissionFee + $wardCost;

        if ($total <= 0) {
            return null;
        }

        $serviceUnitId = optional($billingService)->service_unit_id
            ?: optional(ServiceUnit::where('tenant_id', $tenantId)->where('name', 'Registration')->first())->id;

        $invoiceNumber = GeneralHelper::getModelUniqueOrderlyId([
            'modelNamespace' => BillingLog::class,
            'modelField' => 'invoice_number',
            'prefix' => 'INV-',
            'idLength' => 6,
        ]);

        $billing = BillingLog::create([
            'tenant_id' => $tenantId,
            'created_by' => optional($currentUser)->id,
            'updated_by' => optional($currentUser)->id,
            'visit_id' => $admission->visit_id,
            'patient_id' => $patient->id,
            'invoice_number' => $invoiceNumber,
            'patient_name' => trim($patient->firstname . ' ' . $patient->lastname),
            'billing_date' => now(),
            'service_type_id' => $patient->service_id,
            'service_unit_id' => $serviceUnitId,
            'grand_total' => $total,
            'total_amount' => $total,
            'amount_outstanding' => $total,
            'payment_status' => GeneralEnums::PENDING->value,
        ]);

        if ($admissionFee > 0) {
            BillingLogDetail::create([
                'tenant_id' => $tenantId,
                'billing_id' => $billing->id,
                'service_unit_id' => $serviceUnitId,
                'item_name' => $billingService->name,
                'quantity' => 1,
                'amount' => $admissionFee,
                'status' => GeneralEnums::PENDING->value,
            ]);
        }

        if ($wardCost > 0) {
            BillingLogDetail::create([
                'tenant_id' => $tenantId,
                'billing_id' => $billing->id,
                'service_unit_id' => $serviceUnitId,
                'item_name' => 'Ward Admission Fee - ' . $ward->name,
                'quantity' => 1,
                'amount' => $wardCost,
                'status' => GeneralEnums::PENDING->value,
            ]);
        }

        $admission->update(['billing_id' => $billing->id]);

        return $billing;
    }

    /**
     * Pick the billing service an admission is charged against, preferring the
     * one that matches its type and falling back to the general admission fee.
     *
     * @param  string|null  $admissionType
     * @param  string|null  $tenantId
     * @param  bool  $isEmergency
     * @return \App\Models\BillingService|null
     */
    protected function resolveAdmissionBillingService($admissionType, $tenantId, $isEmergency = false)
    {
        $codes = [];

        if ($isEmergency) {
            $codes[] = BillingService::CODE_EMERGENCY_ADMISSION;
        }

        if ($admissionType) {
            $codes[] = strtoupper(str_replace([' ', '-'], '_', $admissionType)) . '_ADMISSION';
        }

        $codes[] = BillingService::CODE_ADMISSION;

        foreach (array_unique($codes) as $code) {
            $service = BillingService::findByCode($code, $tenantId);

            if ($service) {
                return $service;
            }
        }

        return null;
    }

    /**
     * Register the walk-in patient of an emergency admission.
     *
     * @param  array  $data
     * @param  string|null  $tenantId
     * @param  \App\Models\User|null  $currentUser
     * @return \App\Models\Patient
     */
    protected function registerEmergencyPatient(array $data, $tenantId, $currentUser)
    {
        $tenant = Tenant::current();
        $tenantDomain = $tenant ? $tenant->domain : 'emed';
        $tenantAcronym = strtoupper(substr(trim($tenantDomain), 0, 2)) . 'H';

        $patient = Patient::create([
            'tenant_id' => $tenantId,
            'created_by' => optional($currentUser)->id,
            'patientno' => 'EMED/' . GeneralHelper::generateUniqueRandomId($data['firstname'])
                . '/' . GeneralHelper::generateUniqueRandomId($data['lastname'])
                . '/' . $tenantAcronym,
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'dob' => $data['dob'] ?? null,
            'age' => !empty($data['dob']) ? Carbon::parse($data['dob'])->age : null,
            'gender' => $data['gender'] ?? null,
            'phoneno' => $data['phoneno'] ?? null,
            'homeaddress' => $data['homeaddress'] ?? null,
            'status' => PatientVisitStatusEnums::ADMITTED->value,
        ]);

        if (!empty($data['emergency_contact_name']) || !empty($data['emergency_contact_phone'])) {
            $names = preg_split('/\s+/', trim($data['emergency_contact_name'] ?? ''), 2);

            // The emergency_contact columns are not nullable, so the fields the
            // emergency form does not collect are stored as blanks.
            EmergencyContact::create([
                'patient_id' => $patient->id,
                'firstname' => $names[0] ?? '',
                'lastname' => $names[1] ?? '',
                'gender' => '',
                'phoneno' => $data['emergency_contact_phone'] ?? '',
                'relationship' => '',
            ]);
        }

        return $patient;
    }

    /**
     * Open the visit an emergency admission is attached to.
     *
     * @param  \App\Models\Patient  $patient
     * @param  array  $data
     * @param  string|null  $tenantId
     * @param  \App\Models\User|null  $currentUser
     * @return \App\Models\PatientVisit
     */
    protected function openEmergencyVisit($patient, array $data, $tenantId, $currentUser)
    {
        $serviceId = $data['service_id'] ?? $patient->service_id
            ?: optional(Service::where('tenant_id', $tenantId)->first())->id;

        return PatientVisit::create([
            'tenant_id' => $tenantId,
            'initiated_by' => optional($currentUser)->id,
            'visitno' => 'VIS' . GeneralHelper::generateUniqueRandomId($patient->firstname),
            'patient_id' => $patient->id,
            'service_id' => $serviceId,
            'arrival_date' => now(),
            'visit_date' => now(),
            'status' => PatientVisitStatusEnums::VISIT_INITIATED->value,
            'triage_status' => GeneralEnums::PENDING->value,
        ]);
    }

    /**
     * Keep the patient record in step with the admission.
     *
     * The visit's own status drives the clinical workflow (triage, pharmacy,
     * laboratory) and is deliberately left alone here.
     *
     * @param  \App\Models\AdmittedPatient  $admission
     * @param  string  $status
     * @return void
     */
    protected function syncPatientStatus($admission, $status)
    {
        if ($admission->patient_id) {
            Patient::where('id', $admission->patient_id)->update(['status' => $status]);
        }
    }

    /**
     * The statuses a listing tab is limited to.
     *
     * @param  string|null  $tab
     * @return array<int, string>
     */
    protected function statusesForTab($tab)
    {
        if (empty($tab)) {
            return [];
        }

        return $this->tabs[strtolower(str_replace([' ', '-'], '_', $tab))] ?? [];
    }

    /**
     * Build the next human readable admission reference.
     *
     * @return string
     */
    protected function generateAdmissionNo()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            'modelNamespace' => AdmittedPatient::class,
            'modelField' => 'admission_no',
            'prefix' => 'ADM-',
            'idLength' => 6,
        ]);
    }

    /**
     * Normalize an incoming time into the H:i:s the column stores.
     *
     * @param  string|null  $time
     * @return string|null
     */
    protected function normalizeTime($time)
    {
        if (empty($time)) {
            return null;
        }

        return Carbon::parse($time)->format('H:i:s');
    }

    /**
     * Write an audit log entry for an admission action.
     *
     * @param  \App\Models\AdmittedPatient  $record
     * @param  string  $action
     * @param  string  $logName
     * @param  string  $description
     * @param  array  $oldData
     * @param  array  $newData
     * @return void
     */
    protected function log($record, $action, $logName, $description, array $oldData = [], array $newData = [])
    {
        GeneralHelper::storeAuditLog([
            'causer_id' => optional(Auth::user())->id,
            'action_id' => $record->id,
            'action' => $action,
            'action_type' => "Models\AdmittedPatient",
            'log_name' => $logName,
            'description' => $description,
            'module_accessed' => ListModuleEnums::Admission,
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
     * Present a related user by their most complete name.
     *
     * @param  \App\Models\User|null  $user
     * @return string|null
     */
    protected function userName($user)
    {
        if (!$user) {
            return null;
        }

        return $user->fullname ?: (trim($user->first_name . ' ' . $user->last_name) ?: $user->email);
    }

    /**
     * Present the patient attached to an admission.
     *
     * @param  \App\Models\AdmittedPatient  $record
     * @return string
     */
    protected function patientName($record)
    {
        $patient = $record->patient;

        return trim(optional($patient)->firstname . ' ' . optional($patient)->lastname) ?: 'the patient';
    }

    public function getPatientCareNotes($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $patientId = $request->patient_id;
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $query = CareNote::query()
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('visit_id', $request->visit_id)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $search = '%' . trim($request['search_param']) . '%';
                $searchRaw = trim($request['search_param']);

                $query->where(function ($q) use ($search, $searchRaw) {
                    $q->orWhereHas('patient', function ($q) use ($search) {
                        $q->where('cardno', 'LIKE', $search)
                            ->orWhere('patientno', 'LIKE', $search)
                            ->orWhere('firstname', 'LIKE', $search)
                            ->orWhere('lastname', 'LIKE', $search);
                    })
                        ->orWhereHas('visit', function ($q) use ($search) {
                            $q->where('visitno', 'LIKE', $search);
                        })
                        ->orWhereHas('writer', function ($q) use ($search, $searchRaw) {
                            $q->where('first_name', 'LIKE', $search)
                                ->orWhere('last_name', 'LIKE', $search)
                                ->orWhereRaw(
                                    "CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) LIKE ?",
                                    ["%{$searchRaw}%"]
                                );
                        })
                        ->orWhereHas('updatedBy', function ($q) use ($search, $searchRaw) {  // ← renamed
                            $q->where('first_name', 'LIKE', $search)
                                ->orWhere('last_name', 'LIKE', $search)
                                ->orWhereRaw(
                                    "CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) LIKE ?",
                                    ["%{$searchRaw}%"]
                                );
                        });
                });
            })
            ->when(isset($request['type']), function ($query) use ($request) {
                $query->where('type', $request['type']);
            })
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                // Fix: use consistent casing — camelCase on both sides
                $query->whereBetween('created_at', [$request->startDate, $request->endDate]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                $query->whereBetween('created_at', $dateFilter);
            })
            ->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('created_at', 'ASC');
            })
            ->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('created_at', 'DESC');
            })
            ->with([
                'patient',
                'visit',
                'writer:id,first_name,last_name,email',
                'updatedBy:id,first_name,last_name,email',  // ← renamed
            ]);

        if (!empty($request['paginate'])) {
            return $query->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $query->orderBy('id', 'DESC')->get();
    }

    public function exportPatientCareNotes($careNotes, $format = null)
    {
        $exportData = $careNotes->map(function ($note) {
            return [
                'Firstname'      => $note->patient->firstname ?? 'N/A',
                'Lastname'       => $note->patient->lastname ?? 'N/A',
                'Card No'        => $note->patient->cardno ?? 'N/A',
                'Patient No'     => $note->patient->patientno ?? 'N/A',
                'Note Type'      => $note->type ?? 'N/A',
                // 'Notes'          => $note->notes ?? 'N/A',
                'Written By'     => $note->writer ? $note->writer->firstname . ' ' . $note->writer->lastname : 'N/A',
                'Date Written'   => $note->created_at ? Carbon::parse($note->created_at)->format('Y-m-d H:i:s') : 'N/A',
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'patient_care_notes.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('patient_care_notes.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    public function addPatientCareNote($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $currentUser = Auth::user();

        $careNote = CareNote::create([
            'tenant_id' => $tenantId,
            'patient_id' => $request->patient_id,
            'visit_id' => $request->visit_id,
            'written_by' => $currentUser ? $currentUser->id : null,
            'notes' => $request->notes,
            'type' => $request->type,
        ]);

        return $careNote->load('patient', 'visit', 'writer:id,first_name,last_name,email');
    }

    public function viewPatientCareNotes($id)
    {
        $careNote = CareNote::find($id);
        if (empty($careNote)) {
            throw new \Exception("Care note not found.");
        }
        return $careNote->load('patient', 'visit', 'writer:id,first_name,last_name,email', 'updatedBy:id,first_name,last_name,email');
    }

    public function updatePatientCareNotes($request, $id)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $currentUser = Auth::user();
        $careNote = CareNote::find($id);
        if (empty($careNote)) {
            throw new \Exception("Care note not found.");
        }

        $careNote->update([
            'updatedBy' => $currentUser ? $currentUser->id : $careNote->written_by,
            'notes' => $request->notes,
            'type' => $request->type,
        ]);

        return $careNote->load('patient', 'visit', 'writer:id,first_name,last_name,email', 'updatedBy:id,first_name,last_name,email');
    }

    public function viewPatientVisitDrugs($id)
    {
        $prescribedDrugs = Treatment::where('visit_id', $id)->where('status', GeneralEnums::FULLFILLED)->with('medication')->get();
        if (count($prescribedDrugs) == 0) {
            throw new \Exception("No dispensed drugs found for this visit.");
        }
        return $prescribedDrugs;
    }

    public function getPatientDrugCharts($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $patientId = $request->patient_id;
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $query = DrugChart::query()->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('visit_id', $request->visit_id)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->whereRelation('patient', 'cardno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'patientno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'firstname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'lastname', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })->when(isset($request['status']), function ($query) use ($request) {
                $query->where('status', filter_var($request['status']));
            })
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('created_at', 'ASC');
            })->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('created_at', 'DESC');
            })->with('patient', 'visit', 'drug', 'writer:id,first_name,last_name,email');

        if (!empty($request['paginate'])) {
            return $query->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $query->orderBy('id', 'DESC')->get();
    }

    public function exportPatientDrugCharts($request)
    {
        $exportData = $request->map(function ($chart) {
            return [
                'Firstname'      => $chart->patient->firstname ?? 'N/A',
                'Lastname'       => $chart->patient->lastname ?? 'N/A',
                'Card No'        => $chart->patient->cardno ?? 'N/A',
                'Patient No'     => $chart->patient->patientno ?? 'N/A',
                'Drug Name'      => $chart->drug_name ?? 'N/A',
                'Dosage'         => $chart->dosage ?? 'N/A',
                'Route'          => $chart->route ?? 'N/A',
                'Frequency'      => $chart->frequency ?? 'N/A',
                'Duration'       => $chart->duration ?? 'N/A',
                'Start Date'     => $chart->start_date ? Carbon::parse($chart->start_date)->format('Y-m-d') : 'N/A',
                'Administration' => $chart->administration ?? 'N/A',
                'Notes'          => $chart->notes ?? 'N/A',
                'Status'         => $chart->status ?? 'N/A',
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($request['format']) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'patient_drug_charts.csv');
        }

        if (strtolower($request['format']) === 'pdf') {
            $pdf = Pdf::loadView('exports.drug_charts', ['drugCharts' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('patient_drug_charts.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    public function addPatientDrugChart($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $currentUser = Auth::user();

        $drugChart = DrugChart::create([
            'tenant_id' => $tenantId,
            'patient_id' => $request->patient_id,
            'visit_id' => $request->visit_id,
            'drug_id' => $request->drug_id ?? null,
            'administered_by' => $currentUser ? $currentUser->id : null,
            'drug_name' => $request->drug_name,
            'dosage' => $request->dosage,
            'route' => $request->route,
            'frequency' => $request->frequency,
            'duration' => $request->duration,
            'start_date' => $request->start_date ? Carbon::parse($request->start_date)->format('Y-m-d') : null,
            'administration' => $request->administration,
            'notes' => $request->notes,
            'status' => GeneralEnums::ADMINISTERED->value,
        ]);

        return $drugChart;
    }

    public function viewPatientDrugCharts($id)
    {
        $drugChart = DrugChart::find($id);
        if (empty($drugChart)) {
            throw new \Exception("Drug chart record not found.");
        }
        return $drugChart->load(['patient', 'visit', 'drug', 'writer']);
    }
}
